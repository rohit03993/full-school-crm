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

    public function clearStudentContext(): void
    {
        if (! $this->isActive()) {
            return;
        }

        $data = $this->load();

        session([
            self::SESSION_KEY => [
                'active' => true,
                'messages' => $data['messages'] ?? $this->defaultMessages(),
                'last_student_id' => null,
                'last_student_name' => null,
            ],
        ]);
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
                    ."Tips:\n"
                    ."• Include the student name — e.g. ABHINAV SINGH - homework for 9 Aug 2026\n"
                    ."• Follow up on the same student — e.g. what about 9 Aug 2026?\n"
                    ."• Use **New student** or say “ask about someone else” to switch",
            ],
        ];
    }
}
