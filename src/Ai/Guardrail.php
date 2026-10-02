<?php

namespace FifteenPeas\Support\Ai;

/**
 * The boundary, enforced in code rather than trusted to the prompt: an answer
 * that cites nothing from the docs is not shown. Whatever the model said, if
 * it could not point at a passage, it came from somewhere other than the docs.
 */
class Guardrail
{
    public function declines(Answer $answer): bool
    {
        return $answer->refused
            || $answer->citations === []
            || trim($answer->text) === '';
    }
}
