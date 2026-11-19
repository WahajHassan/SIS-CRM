@extends('legal.shell')

@section('title', 'Terms')
@section('heading', 'Terms of use')

@section('content')
    <p>
        These terms cover the use of this CRM by the people given accounts on it.
    </p>

    <div class="note">
        <b>For {{ $companyName }}:</b> this is a working draft covering how the
        software is used, not a commercial contract. Before you sell access to this
        CRM, a lawyer needs to add the parts that carry money and risk — what you
        charge and when, what uptime you promise, who owns what, how either side
        ends the arrangement, which country's law applies, and the limits on your
        liability. Those cannot be written from the code.
    </div>

    <h2>Your account</h2>
    <ul>
        <li>An account belongs to one person. Sharing a password means the record of who did what stops being true, and that record is what pay and disputes rest on.</li>
        <li>Keep your password to yourself. If you think somebody else has it, change it and tell whoever administers the CRM.</li>
        <li>Sign-ins are logged with the time and the IP address.</li>
    </ul>

    <h2>What the CRM is for</h2>
    <p>
        Work. The customer records, leads and deals in it belong to the company, not
        to the person who entered them. Taking a copy of them for anything other than
        the company's own work — including after leaving — is not permitted, and
        exports are logged.
    </p>

    <h2>What is expected</h2>
    <ul>
        <li>Record attendance honestly. Clocking in for somebody else is the reason the fingerprint check exists.</li>
        <li>Write notes on a lead that you would be content for the customer to read.</li>
        <li>Quick Huddle shows your screen to a colleague. Do not accept a request while anything private is open, and do not ask for one without a reason.</li>
        <li>The break room is for breaks.</li>
    </ul>

    <h2>Accuracy</h2>
    <p>
        Salary, incentives and payslips are worked out from the attendance on file. If
        a figure looks wrong, say so — do not assume the system is right. The day-by-day
        breakdown behind every figure is there so it can be checked.
    </p>

    <h2>Access can be changed or removed</h2>
    <p>
        Whoever administers this CRM can change what an account can reach, disable it,
        or remove it. Removing an account does not remove the attendance, leave or pay
        records attached to it; those are kept because they are the company's record of
        work done.
    </p>

    <h2>Availability</h2>
    <p>
        The CRM is updated from time to time, and may be briefly unavailable while that
        happens. No guarantee of uninterrupted availability is made here — if one is
        needed, it belongs in a written agreement.
    </p>
@endsection
