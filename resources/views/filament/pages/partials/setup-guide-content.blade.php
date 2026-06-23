<div class="space-y-6 text-sm text-gray-700 dark:text-gray-300">
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Who is this for?</h2>
        <p class="mt-2">This CRM is for <strong>one institute</strong> — a school, coaching center, or college. All day-to-day settings (name, logo, labels, courses, staff) are changed from the admin menus below. You never need to edit code or server files.</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">First-time setup</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5">
            <li>Log in with your Super Admin account.</li>
            <li>Complete the <strong>Setup Wizard</strong> — choose institute type, enter your name, phone, and address.</li>
            <li>Open <strong>Institute Setup</strong> and work through the checklist (sessions, courses, site content, staff).</li>
            <li>Add your first <strong>Academic Session</strong>, then <strong>Courses</strong> and <strong>Batches</strong> before enrolling students.</li>
            <li>Create <strong>Staff</strong> logins so counsellors and accountants can use the CRM.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-500">Each screen in the sidebar also shows a short hint under the title — read it when you are unsure what to enter.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-500/20 dark:bg-blue-500/5">
            <h3 class="font-semibold text-blue-900 dark:text-blue-200">School</h3>
            <ul class="mt-2 space-y-1 text-blue-900/80 dark:text-blue-100/80">
                <li>Type: <strong>School</strong></li>
                <li>Labels: Class, Section, Roll No.</li>
                <li>Courses: Class 10, 11, 12 streams</li>
                <li>Batches: 12-A Morning, etc.</li>
            </ul>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
            <h3 class="font-semibold text-amber-900 dark:text-amber-200">Coaching</h3>
            <ul class="mt-2 space-y-1 text-amber-900/80 dark:text-amber-100/80">
                <li>Type: <strong>Coaching</strong></li>
                <li>Labels: Programme, Batch, Reg. No.</li>
                <li>Courses: JEE, NEET, Foundation</li>
                <li>Batches: Evening batch, Dropper batch</li>
            </ul>
        </div>
        <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-4 dark:border-violet-500/20 dark:bg-violet-500/5">
            <h3 class="font-semibold text-violet-900 dark:text-violet-200">College</h3>
            <ul class="mt-2 space-y-1 text-violet-900/80 dark:text-violet-100/80">
                <li>Type: <strong>College</strong></li>
                <li>Labels: Programme, Semester, Enrollment No.</li>
                <li>Courses: B.Com, B.Sc, etc.</li>
                <li>Batches: Sem 2 — Section A</li>
            </ul>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Where to change what</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="pb-2 pr-4 font-semibold text-gray-950 dark:text-white">What</th>
                        <th class="pb-2 pr-4 font-semibold text-gray-950 dark:text-white">Admin menu</th>
                        <th class="pb-2 font-semibold text-gray-950 dark:text-white">What to enter</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ([
                        ['Institute type', 'Settings → Institute Setup', 'School, coaching, or college — sets default labels'],
                        ['Name, logo, website', 'Website → Site Content', 'Institute name, phone, homepage text, gallery photos'],
                        ['Meeting for choices', 'Settings → Meeting for', 'Enquiry, Admission, Marketing, Fees — shown on lead forms'],
                        ['Rename labels', 'Settings → Terminology', 'e.g. Class instead of Course, Section instead of Batch'],
                        ['Extra student fields', 'Settings → Custom Fields', 'Blood group, transport route, scholarship, etc.'],
                        ['Academic year', 'Academics → Academic Sessions', 'e.g. 2025–26 — mark one as current'],
                        ['Programmes', 'Academics → Courses', 'Classes, degrees, or coaching programmes you offer'],
                        ['Groups of students', 'Academics → Batches', 'Sections, batches, or semesters under a course'],
                        ['Receipt PDF text', 'Settings → Institute Settings', 'Footer line on fee receipts and ID cards'],
                        ['WhatsApp', 'Settings → WhatsApp Settings', 'Connect your WhatsApp account and sync message templates'],
                        ['Staff logins', 'Administration → Staff', 'Name, email, password, and role for each team member'],
                    ] as [$what, $menu, $purpose])
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-950 dark:text-white">{{ $what }}</td>
                            <td class="py-2 pr-4">{{ $menu }}</td>
                            <td class="py-2 text-gray-600 dark:text-gray-400">{{ $purpose }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Custom student fields — how to use</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5">
            <li>Go to <strong>Settings → Custom Fields</strong>.</li>
            <li>Add a field — enter a label and pick a type (text, number, date, or dropdown).</li>
            <li>Click Save — the field appears on every student when staff open <strong>Edit Details</strong>.</li>
            <li>Turn on <strong>Required</strong> only if staff must fill it every time.</li>
        </ol>
    </div>

    <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-5 dark:border-primary-500/20 dark:bg-primary-500/5">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Helpful tips</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Institute name and phone for the website and receipts — set them under <strong>Website → Site Content</strong>.</li>
            <li>Not sure what a field means? Look for the grey hint under the page title or the small text below each input.</li>
            <li>For server or login issues, contact your CRM provider — you do not need to change anything on the server yourself.</li>
        </ul>
    </div>
</div>
