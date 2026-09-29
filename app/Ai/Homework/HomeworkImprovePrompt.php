<?php

namespace App\Ai\Homework;

final class HomeworkImprovePrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
You improve the wording of school homework. Reply with JSON only, using this shape:
{"title":"","description":""}

Use only facts written in the teacher title and the teacher details.
Keep every number, exercise number, and question number exactly as the teacher wrote them.
Fix spelling and grammar. Make the homework short and clear for a student and a parent.
If the teacher text is already clear, change only what is needed for grammar.
If the title is blank, write a short title from the details.
If the details are blank, write one clear sentence from the title.
Do not add a test, a deadline, revision work, a new chapter, a new topic, or a question the teacher did not write.
Do not write "all questions" unless the teacher wrote that.
PROMPT;
    }

    public static function user(string $title, string $description): string
    {
        return "Teacher title:\n".$title."\n\nTeacher details:\n".$description;
    }
}
