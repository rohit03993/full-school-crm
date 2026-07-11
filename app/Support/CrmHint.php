<?php

namespace App\Support;

use Filament\Forms\Components\Placeholder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CrmHint
{
    /** @var array<string, string> */
    private const HINTS = [
        'dashboard' => 'Owner overview — students, today’s attendance, fees by batch, leads, and admissions. Staff see Assigned to Call and calling stats here.',
        'setup.wizard' => 'Complete this once when you first log in. Enter your institute name, contact details, and preferred labels — everything saves automatically.',
        'setup.institute' => 'Pick school, coaching, or college. This controls programme types on the website and enquiry forms. Use the checklist below for remaining steps.',
        'setup.guide' => 'Step-by-step reference for Super Admin. Use the table below to find where to change each setting. Every screen also shows hints under the title.',
        'setup.backups' => 'Full disaster-recovery archives: entire database plus all photos, documents, receipts, homework, logos, and WhatsApp media. Connect Google Drive for automatic off-site copies. Restore by uploading a zip here after reinstall — matching APP_KEY required.',
        'setup.terminology' => 'Rename labels to match your institute — e.g. Class instead of Course, Section instead of Batch. Empty fields use sensible defaults for your institute type.',
        'setup.custom_fields' => 'Optional extra fields on student or enquiry forms (e.g. blood group, previous school). Not used for exam marks — use Exam results → Upload marks (Excel).',
        'setup.meeting_for' => 'Choose what appears in the Meeting for dropdown on Search Student and enquiry forms — e.g. Enquiry, Admission, Marketing. Reorder, rename, add, or hide options here.',
        'setup.site_content' => 'Controls the public website: logo, phone number, homepage text, and gallery. Changes go live immediately after Save.',
        'setup.institute_settings' => 'Receipt and ID card PDF branding. Institute name and phone come from Website → Site Content — set logo here for printed documents.',
        'setup.meta_whatsapp' => 'Each school stores its own Meta WhatsApp credentials in this CRM (Taskbook uses Taskbook\'s WABA, Folks India uses theirs). When enabled, all sends route through Meta Cloud API — no separate waservice server needed.',
        'setup.whatsapp' => 'Map punch IN/OUT, post-call, and batch attendance to live API campaigns. Create campaigns under Live campaigns first, then pick them here.',
        'whatsapp.live_campaigns' => 'Live API campaigns — like AiSensy campaignName. Go live to use in automations, bulk sends, and external POST triggers.',
        'whatsapp.live_campaigns.create' => 'Pick an approved template and a unique campaign name. After saving, open the campaign and click Go live.',
        'meta_whatsapp.messages' => 'Outbound sends and parent replies logged via Meta webhooks. Delivery status updates appear here.',
        'meta_whatsapp.inbox' => 'WhatsApp inbox — all recent chats (students and unknown numbers). Select a conversation to reply, send templates, or open a student profile.',
        'meta_whatsapp.analytics' => 'Spend and volume from Meta pricing_analytics (official) plus per-campaign estimates in CRM. Filter by date like AiSensy campaign reports.',
        'students.profile.messages' => 'Send an approved template from the left panel — only the fields that template needs appear. Student name and roll number are pre-filled when possible.',
        'setup.biometric' => 'EasyTimePro writes punches to punch_logs on the same MySQL server. Match device employee ID to student roll number, then configure parent messages under WhatsApp (Pal Digital) → Automations.',

        'courses.list' => 'Programme fees and subjects — open from Classes & sections → Programme & fee.',
        'courses.create' => 'Use a short unique code (e.g. SCH-12-SCI). Fee and duration show on the website and flow into admissions. Optionally add subjects shared by every section under this programme.',
        'batches.list' => 'Sections within a programme — managed under Academics → Classes & sections.',
        'class_sections.list' => 'Each class (programme) has subjects once — all sections share them. Use the Subjects button on each class, then assign teachers per section.',
        'class_section.create' => 'Create programme/class and section/batch together — e.g. Class 12 + Section A.',
        'batches.create' => 'Link to a course and academic session. Assign a trainer (staff) if you track who owns the batch. Optionally assign class lead and subject teachers when subjects exist on the programme.',
        'sessions.list' => 'Create the academic year (e.g. 2025–26) and mark one as current. Batches and enrollments use the current session.',
        'enquiries.list' => 'Super Admin only — filter leads, bulk-assign staff for calling (checkboxes + Bulk actions). Staff see assignments under Assigned to Call.',
        'students.search' => 'Fastest way to open a student profile when you know the mobile number. For browsing all institute leads, use All Leads; for your calling list, use Assigned to Call.',
        'staff.list' => 'Create logins with one or more job roles (Counsellor, Accountant, etc.). Permissions combine — tick all four for full operations access.',
        'staff.create' => 'Set mobile, password, and tick every role this person needs. You can assign 1, 2, 3, or all job roles on one account.',
        'staff.edit' => 'Add or remove job roles anytime. Super Admin is separate — full institute control.',
        'admissions.list' => 'Oversight list for admission forms. Staff submit documents here; only Super Admin can approve and create the roll number.',
        'whatsapp.campaigns' => 'Send template messages to a batch or whole course. Student name and roll number fill in automatically per recipient.',
        'import.bulk' => 'For migrating old student data only — not for day-to-day admissions. Creates enrolled students in one step and skips the normal enquiry → call → admission flow. Use Convert to Admission for new students.',
        'reports' => 'Export summaries for management. Filter by date, batch, and course before downloading. Detail reports export at most 5,000 rows — narrow the date range if you need a full export.',
        'attendance' => 'One screen for daily attendance. Live punches from EasyTimePro mark Present automatically and send IN/OUT WhatsApp. Switch to Manual batch to tap IN when students arrive and OUT when they leave — each tap saves immediately and can notify parents.',
        'attendance.batch' => 'Use Academics → Attendance → Manual batch. Tap IN on arrival and OUT on departure. Parent WhatsApp sends on each action when enabled.',
        'attendance.punch' => 'Use Academics → Attendance → Live punches. First punch = IN (Present + IN WhatsApp); next valid punch = OUT (OUT WhatsApp).',
        'homework.list' => 'Upload homework for one batch at a time. Students view it in the student portal (mobile login). Optional WhatsApp sends the portal link with name and roll number.',
        'homework.create' => 'PDF or image attachment optional. Turn on WhatsApp to notify parents with a link — they log in to the portal to view homework.',
        'attendance.session' => 'Legacy — workshop attendance is no longer used. Use Academics → Attendance for daily class roll call.',
        'activity.types' => 'Exam category used when entering marks (default: Exam).',
        'followups' => 'Students due for a follow-up call today. Open the profile to log the call and schedule the next date.',
        'call.queue' => 'Your assigned calling list. Log each call from the student profile so history stays in one place.',
        'call.report' => 'Daily and weekly call stats for your team. Filter by staff and date range before exporting.',
        'assigned.to.call' => 'Leads the admin assigned to you for calling. Open a profile to log the call.',
        'assigned.to.me' => 'Work assigned to you — campus meetings and support cases currently on your name.',
        'work.supervisor.meetings' => 'Campus meetings assigned to you personally. Use the All cases tab for institute-wide case oversight.',
        'cases.my' => 'Support cases assigned to you for enrolled students — fees, academic issues, complaints. Open the student profile to transfer, call, or close.',
        'cases.all' => 'All enrolled-student support cases across the institute. Filter by status, type, or assignee.',
        'teaching.assignments' => 'Your class assignments and open exams — enter subject marks or submit to admin as class lead.',
        'exam_windows.list' => 'Create exams from class subjects. Teachers enter marks per subject; class lead submits; admin approves before publish.',
        'exam_windows.create' => 'Pick section and exam name — one mark-entry sheet is created per programme subject automatically.',
        'exam_windows.detail' => 'Track mark entry, submit to admin, approve, then publish results to the student portal.',
        'assigned.meetings' => 'Campus visits assigned to you. Open meetings need to be closed with notes. Closed tab shows your completed meeting history.',
        'campus.visits' => 'Admin only — all campus visits by day or month. Filter prospect vs enrolled, see first-time visitors and repeat visitors. Staff log visits from the student profile.',
        'students.profile' => 'One place for enquiries, visits, admission, fees, batch, attendance, and documents. Use Edit Details to update the student.',
        'students.list' => 'All students in the system. Use Add Student for one-by-one enrollment, or Search Student for lookup by mobile or roll number.',
        'sessions.create' => 'Add the academic year (e.g. 2025–26). Mark exactly one session as current for new batches and enrollments.',
        'sessions.edit' => 'Change dates or rename the session. Set Current only on the active academic year.',
        'courses.subjects' => 'Add English, Maths, Science, etc. Every section under this class uses the same subject list for exams and teachers.',
        'courses.edit' => 'Update programme name, fee, duration, or website visibility. Open the Subjects section below to manage subjects.',
        'batches.edit' => 'Edit section details and assign teachers. Subjects are managed on the parent class — use Manage subjects in the page header.',
        'activity.types.create' => 'Create an exam category. Enable “Records marks & scores” so it appears when uploading marks.',
        'activity.types.edit' => 'Rename the exam category or adjust custom fields if needed.',
        'activity.sessions.list' => 'Upload exam marks via Excel, create exam windows from programme subjects, or enter marks per subject.',
        'activity.marks.review' => 'Read-only mark sheet for this exam. Publish after the exam window is approved.',
        'activity.sessions.create' => 'Schedule one exam subject manually — prefer Create exam for full tests.',
        'activity.attendance' => 'Mark Present or Absent, then enter marks for each student who appeared.',
        'activity.marks.import' => 'Enter test name and date, pick exam type, upload Excel. Roll numbers match students automatically.',
        'whatsapp.templates' => 'Create templates here and submit to Meta for approval. Sync refreshes APPROVED / PENDING / REJECTED status. Map {{1}} to student name before bulk campaigns.',
        'whatsapp.templates.create' => 'Type {{1}}, {{2}}, … in the message body — sample fields for each variable appear automatically (like AiSensy). {{1}} is usually student name.',
        'whatsapp.campaigns.create' => 'Pick template, audience (batch or course), fill message fields, then send or save as draft.',
        'audit.logs' => 'Read-only history of important changes — fees, admissions, profile edits. For Super Admin oversight.',
    ];

    /** @var array<string, string> */
    private const FIELD_HINTS = [
        'record_prefix' => 'Used in enquiry, admission, and roll numbers — e.g. CRM-ENQ-2026-000001. Letters and numbers only.',
        'institute_type' => 'School = classes/sections. Coaching = exam batches. College = degree programmes. You can change this later.',
        'sample_programmes' => 'Adds starter courses you can edit or delete. Turn off if you prefer to create courses yourself.',
        'course_code' => 'Short unique code for reports and enquiries — e.g. SCH-12-COM, COACH-JEE-1Y.',
        'course_fee' => 'Default fee for this programme. Staff can adjust per student at admission.',
        'batch_shift' => 'Morning / Evening / etc. Helps staff pick the right batch on enquiries.',
        'mobile_unique' => 'One mobile number per student in the system. Used for portal login and WhatsApp.',
        'custom_field_key' => 'Optional internal key. Leave blank to auto-generate from the label.',
        'student_photo' => 'JPG or PNG, max 5 MB. Replaces the current photo. Regenerate the ID card after saving if one was already issued.',
        'student_documents' => 'JPG, PNG, or PDF, max 5 MB each. Replaces the existing file for that document type.',
        'regenerate_id_card' => 'When on, a new ID card PDF is created immediately using the updated photo.',
    ];

    public static function get(string $key, ?string $fallback = null): string
    {
        return self::HINTS[$key] ?? $fallback ?? '';
    }

    public static function field(string $key, ?string $fallback = null): string
    {
        return self::FIELD_HINTS[$key] ?? $fallback ?? '';
    }

    public static function text(string $key): string
    {
        return self::get($key);
    }

    public static function box(string $key, ?string $override = null): HtmlString
    {
        $message = $override ?? self::get($key);

        if ($message === '') {
            return new HtmlString('');
        }

        return new HtmlString(view('filament.components.crm-hint', [
            'message' => $message,
        ])->render());
    }

    public static function placeholder(string $key, ?string $override = null): Placeholder
    {
        return Placeholder::make('crm_hint_'.$key)
            ->label('')
            ->content(fn (): HtmlString => self::box($key, $override))
            ->columnSpanFull()
            ->hidden(fn (): bool => ($override ?? self::get($key)) === '');
    }

    public static function navigationTooltip(string $key): ?string
    {
        $text = self::get($key);

        return $text !== '' ? $text : null;
    }
}
