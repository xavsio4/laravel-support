<?php

namespace FifteenPeas\Support\Console;

use FifteenPeas\Support\Docs\DocsRepository;
use FifteenPeas\Support\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexDocsCommand extends Command
{
    protected $signature = 'support:index';

    protected $description = 'Sync the support docs folder into the knowledge base';

    public function handle(DocsRepository $repository): int
    {
        $files = $repository->all();

        $created = $updated = 0;

        DB::transaction(function () use ($files, &$created, &$updated) {
            foreach ($files as $file) {
                $document = Document::firstOrNew(['slug' => $file->slug]);

                if ($document->exists && $document->checksum === $file->checksum()) {
                    continue;
                }

                $document->exists ? $updated++ : $created++;

                $document->fill([
                    'title' => $file->title,
                    'url' => $file->url,
                    'content' => $file->content,
                    'checksum' => $file->checksum(),
                    'tokens' => $file->tokens(),
                ])->save();
            }
        });

        $deleted = Document::whereNotIn('slug', array_map(fn ($f) => $f->slug, $files))->delete();

        $total = Document::sum('tokens');
        $this->info(sprintf(
            '%d documents (%d new, %d updated, %d removed), about %s tokens.',
            count($files), $created, $updated, $deleted, number_format($total),
        ));

        if ($total > config('support.max_corpus_tokens')) {
            // Not an error: the corpus still works, it just costs more per
            // question and edges toward the context limit.
            $this->warn(sprintf(
                'The corpus is over support.max_corpus_tokens (%s). Every question sends all of it: time for a retriever.',
                number_format(config('support.max_corpus_tokens')),
            ));
        }

        if (count($files) === 0) {
            $this->warn('No docs found: the assistant will decline every question.');
        }

        return self::SUCCESS;
    }
}
