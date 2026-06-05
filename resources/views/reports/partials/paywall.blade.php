{{-- Shown above the (free) preview when the download is locked. The report
     stays visible on screen, but the print output is replaced with a notice so
     Cmd/Ctrl+P can't save the report as a PDF for free. --}}
<div class="paywall paywall-banner">
    <div class="paywall-card">
        <div class="paywall-lock">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
        </div>
        <h1>Download your report</h1>
        <p class="paywall-title">{{ $report->title ?: $report->module_title ?: 'Your report' }}</p>

        @if ($downloadPrice > 0)
            <div class="paywall-price">Rs.&nbsp;{{ number_format($downloadPrice, 2) }}</div>
            <p class="paywall-sub">Preview is free below. Pay to unlock the PDF download.</p>

            <div class="paywall-methods">
                @foreach ($enabledGateways as $gateway)
                    <form method="POST" action="{{ route('reports.pay', ['report' => $report, 'gateway' => $gateway]) }}">
                        @csrf
                        <button type="submit" class="pay-method pay-method-{{ $gateway }}">
                            <span class="pay-method-name">{{ $gateway === 'esewa' ? 'eSewa' : 'Khalti' }}</span>
                            <span class="pay-method-go">Pay&nbsp;&rarr;</span>
                        </button>
                    </form>
                @endforeach
            </div>

            <p class="paywall-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13" style="vertical-align:-2px"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                Secure payment — you'll return here to download once it's confirmed.
            </p>
        @else
            <p class="paywall-warn">Paid downloads are enabled, but no price has been set yet. Please set a download price in <strong>Admin&nbsp;→&nbsp;Payments</strong>.</p>
        @endif
    </div>
</div>

{{-- Replaces the printed output so a free Cmd/Ctrl+P can't capture the report. --}}
<div id="paywall-print" aria-hidden="true">
    <div>
        <h1>Payment required</h1>
        <p>This report can only be downloaded after payment. Return to the app and choose a payment method to continue.</p>
    </div>
</div>
<style>
    #paywall-print { display: none; }
    @media print {
        body > *:not(#paywall-print) { display: none !important; }
        #paywall-print {
            display: flex !important; align-items: center; justify-content: center;
            min-height: 90vh; text-align: center; font-family: system-ui, sans-serif;
        }
        #paywall-print h1 { font-size: 20px; margin: 0 0 10px; }
        #paywall-print p { color: #444; font-size: 13px; max-width: 360px; margin: 0 auto; }
    }
</style>
<script>
    // Best-effort nudge; the print stylesheet above is the real block.
    window.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            alert('This report requires payment before it can be downloaded.');
        }
    });
</script>
