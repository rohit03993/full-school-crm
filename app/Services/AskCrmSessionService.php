<?php

namespace App\Services;

class AskCrmSessionService
{
    public const SESSION_KEY = 'ask_crm';

    public function isActive(): bool
    {
        return (bool) data_get(session(self::SESSION_KEY), 'active', false);
    }

    /**
     * @return array{
     *     active?: bool,
     *     messages?: list<array{role: string, text: string}>,
     *     last_student_id?: ?int,
     *     last_student_name?: ?string
     * }
     */
    public function load(): array
    {
        $data = session(self::SESSION_KEY);

        return is_array($data) ? $data : [];
    }

    /**
     * @param  list<array{role: string, text: string}>  $messages
     */
    public function save(array $messages, ?int $lastStudentId, ?string $lastStudentName): void
    {
        session([
            self::SESSION_KEY => [
                'active' => true,
                'messages' => $messages,
                'last_student_id' => $lastStudentId,
                'last_student_name' => $lastStudentName,
            ],
        ]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return list<array{role: string, text: string}>
     */
    public function defaultMessages(): array
    {
        return [
            [
                'role' => 'assistant',
                'text' => "Hi — I’m Ask CRM.\n\n"
                    ."I read the same data as the student profile — attendance, homework, fees, calls, visits, exams, and more.\n\n"
                    ."Examples:\n"
                    ."• homework for Abhinav Singh\n"
                    ."• what about 9 Aug 2026?\n"
                    ."• then: and cases open for this student",
            ],
        ];
    }
}
