<?php

namespace App\Ai\Homework;

final class HomeworkImprovePrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
You write a homework note that a student and a parent can read. Reply with JSON only, using this shape:
{"title":"","description":""}

The description must be a short letter with a blank line between each part:
1. Start with "Dear Students,"
2. Say today's homework for the class and subject you are given. Copy the class and subject exactly. If the class or subject is blank, leave that part out.
3. Write the teacher's work as clear full sentences. Put the topic and the exercise in the same sentence. Correct every spelling mistake. Never copy a misspelled word.
4. Keep every number, exercise number, and question number exactly as the teacher wrote them.
5. Add "Instructions for Students:" and one or two correct sentences. Name the exercise or question numbers again, and ask the student to write the solutions in the homework notebook. Do not write the words "that same work".
6. End with these lines:
Warm regards,
Then the teacher name you are given, on its own line. Copy that name exactly. Do not write "Subject Teacher" when a teacher name is given. If no teacher name is given, write "Subject Teacher".
Then the school name, if a school name is given.

The title should look like "Topic – Exercise 1.1" when the teacher wrote a topic and an exercise. Do not start the title with the subject name.
If the title is blank, make a short title from the subject and the work the teacher wrote.

Do not add a test, a deadline, a new chapter, a new topic, a new exercise, or a question the teacher did not write.
Do not write "all questions" unless the teacher wrote that.
Do not change the subject. If the subject is Physics, do not call it Chemistry.
PROMPT;
    }

    /**
     * @param  array{class_label?: string, subject_label?: string, school_name?: string, teacher_name?: string}  $context
     */
    public static function user(string $title, string $description, array $context = []): string
    {
        $school = trim((string) ($context['school_name'] ?? ''));
        $class = trim((string) ($context['class_label'] ?? ''));
        $subject = trim((string) ($context['subject_label'] ?? ''));
        $teacher = trim((string) ($context['teacher_name'] ?? ''));

        return "School: ".($school !== '' ? $school : '(not given)')
            ."\nClass: ".($class !== '' ? $class : '(not given)')
            ."\nSubject: ".($subject !== '' ? $subject : '(not given)')
            ."\nTeacher name: ".($teacher !== '' ? $teacher : '(not given)')
            ."\n\nTeacher title:\n".$title
            ."\n\nTeacher details:\n".$description;
    }
}
