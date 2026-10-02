<?php

namespace FifteenPeas\Support\Console;

use FifteenPeas\Support\Ai\DocsAnswerer;
use FifteenPeas\Support\Ai\Guardrail;
use FifteenPeas\Support\Ai\Retriever;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Asks the real model the questions in the eval file and checks the boundary:
 * "answer" questions must come back cited, "decline" questions must not.
 * Every run costs money, so it is run on purpose, not in CI.
 */
class EvalCommand extends Command
{
    protected $signature = 'support:eval
        {file=tests/support-eval.yaml : Eval file, relative to the app root}
        {--only= : Run only "answer" or "decline"}';

    protected $description = 'Check the assistant answers in-scope questions and declines the rest (calls the API)';

    public function handle(Retriever $retriever, DocsAnswerer $answerer, Guardrail $guardrail): int
    {
        $path = base_path($this->argument('file'));

        if (! is_file($path)) {
            $this->error("Eval file not found: {$path}");

            return self::FAILURE;
        }

        $suite = Yaml::parseFile($path);
        $cases = [];

        foreach (['answer', 'decline'] as $expect) {
            if ($this->option('only') && $this->option('only') !== $expect) {
                continue;
            }

            foreach ($suite[$expect] ?? [] as $question) {
                $cases[] = [$expect, (string) $question];
            }
        }

        if ($cases === []) {
            $this->warn('No questions to run.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm(count($cases).' questions will be sent to '.config('support.ai.model').'. Continue?', true)) {
            return self::SUCCESS;
        }

        $rows = [];
        $failed = 0;
        $tokens = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0];

        foreach ($cases as [$expect, $question]) {
            try {
                $answer = $answerer->answer($retriever->documentsFor($question), [], $question);
                $declined = $guardrail->declines($answer);
                $got = $declined ? 'decline' : 'answer';

                $tokens['input'] += $answer->usage['input_tokens'] ?? 0;
                $tokens['output'] += $answer->usage['output_tokens'] ?? 0;
                $tokens['cache_read'] += $answer->usage['cache_read_input_tokens'] ?? 0;
                $tokens['cache_write'] += $answer->usage['cache_creation_input_tokens'] ?? 0;

                $detail = $declined
                    ? mb_strimwidth(str_replace("\n", ' ', $answer->text), 0, 60, '…')
                    : collect($answer->citations)->pluck('slug')->implode(', ');
            } catch (Throwable $e) {
                $got = 'error';
                $detail = mb_strimwidth($e->getMessage(), 0, 60, '…');
            }

            $pass = $got === $expect;
            $failed += $pass ? 0 : 1;

            $rows[] = [$pass ? '<info>PASS</info>' : '<error>FAIL</error>', $expect, mb_strimwidth($question, 0, 60, '…'), $detail];
        }

        $this->table(['', 'Expected', 'Question', 'Sources / reply'], $rows);
        $this->line(sprintf(
            'Tokens: %s input, %s output, %s cache read, %s cache write.',
            number_format($tokens['input']), number_format($tokens['output']),
            number_format($tokens['cache_read']), number_format($tokens['cache_write']),
        ));

        if ($failed > 0) {
            $this->error("{$failed} of ".count($cases).' failed.');

            return self::FAILURE;
        }

        $this->info('All '.count($cases).' passed.');

        return self::SUCCESS;
    }
}
