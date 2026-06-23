<script>
(function () {
    const STORAGE_KEY = 'pendingCallLog';
    const MAX_AGE_MS = 15 * 60 * 1000;

    function readPending() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);

            if (! raw) {
                return null;
            }

            const data = JSON.parse(raw);

            if (! data || typeof data.setAt !== 'number') {
                sessionStorage.removeItem(STORAGE_KEY);

                return null;
            }

            if (Date.now() - data.setAt > MAX_AGE_MS) {
                sessionStorage.removeItem(STORAGE_KEY);

                return null;
            }

            const studentId = parseInt(data.studentId, 10);

            if (Number.isNaN(studentId) || studentId <= 0) {
                sessionStorage.removeItem(STORAGE_KEY);

                return null;
            }

            return { ...data, studentId };
        } catch (error) {
            sessionStorage.removeItem(STORAGE_KEY);

            return null;
        }
    }

    window.CrmPendingCall = {
        start(studentId, name, phone, telUrl, notConnectedAttempts) {
            if (! telUrl) {
                window.alert('No phone number for this student.');

                return;
            }

            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    studentId: studentId,
                    name: name || '',
                    phone: phone || '',
                    notConnectedAttempts: parseInt(notConnectedAttempts || 0, 10) || 0,
                    setAt: Date.now(),
                }));
            } catch (error) {}

            window.location.href = telUrl;
        },
        tryOpen() {
            const data = readPending();

            if (! data) {
                return;
            }

            if (typeof Livewire !== 'undefined' && typeof Livewire.dispatch === 'function') {
                Livewire.dispatch('open-pending-call-log', { studentId: data.studentId });
            }
        },
        clearPending() {
            sessionStorage.removeItem(STORAGE_KEY);
        },
    };

    let hadHidden = false;
    let firstVisibleHandled = false;

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            hadHidden = true;

            return;
        }

        if (document.visibilityState !== 'visible') {
            return;
        }

        if (! firstVisibleHandled) {
            firstVisibleHandled = true;
            window.CrmPendingCall.tryOpen();

            return;
        }

        if (hadHidden) {
            hadHidden = false;
            window.CrmPendingCall.tryOpen();
        }
    });

    document.addEventListener('livewire:navigated', function () {
        window.CrmPendingCall.tryOpen();
    });
})();
</script>
