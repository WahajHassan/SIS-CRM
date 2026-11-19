@extends('legal.shell')

@section('title', 'Privacy')
@section('heading', 'Privacy')

@section('content')
    <p>
        This page describes what this CRM records, why, and what happens to it. It is
        written from how the system actually works rather than from a template, so
        that the people whose work it records can read it and recognise the truth.
    </p>

    <div class="note">
        <b>For {{ $companyName }}:</b> this describes the software accurately, but it
        is not legal advice and it is not a complete privacy notice. Before you rely
        on it with customers, have a lawyer in your jurisdiction read it and add the
        parts only you can supply — who your data controller is, how long you keep
        records, and the rights your customers have where you operate.
    </div>

    <h2>What is recorded about the people who work here</h2>
    <ul>
        <li><b>Account details</b> — name, username, email address, role, and the password, which is stored only as a hash and cannot be read back.</li>
        <li><b>Attendance</b> — when each person clocked in and out, their breaks, and the lateness worked out from those times. This is what salary is calculated from.</li>
        <li><b>Leave</b> — requests, who approved them, and the balance remaining.</li>
        <li><b>Employee file</b> — whatever HR fills in: department, designation, contact number, and documents uploaded against the record.</li>
        <li><b>Work</b> — leads called, handovers made, deals closed, training watched, messages sent in Team Chat.</li>
        <li><b>Sign-ins</b> — the time and IP address of each sign-in, so an account used by somebody else can be spotted.</li>
    </ul>

    <h2>What is recorded about customers and leads</h2>
    <ul>
        <li>Business name, contact name, phone, email, address, trade and location.</li>
        <li>Notes written by whoever made the call, and the stage the lead reached.</li>
        <li>For a closed deal: the amount, what was charged, and what remains.</li>
    </ul>

    <h2>Card details</h2>
    <p>
        Where a card is recorded against a deal, the number, name, expiry, security
        code and billing address are <b>encrypted in the database</b> and cannot be
        read by looking at it directly. They are never included in any export, and
        they are not written to logs.
    </p>

    <h2>Fingerprint and face</h2>
    <p>
        Where clocking in is set to require it, the check happens <b>inside the
        device</b>. The phone or laptop reads the finger or face, decides for itself
        whether it is the owner, and then signs a one-time challenge. What this CRM
        stores is a public key and nothing else — <b>no fingerprint, no face, and
        nothing from which either could be reconstructed</b>, even if the whole
        database were taken.
    </p>

    <h2>Screen sharing</h2>
    <p>
        Quick Huddle shows one person's screen to another only after the person
        sharing has picked what to share and agreed to it, and it stops the moment
        either of them ends it. Nothing is recorded or kept; the picture travels
        directly between the two computers.
    </p>

    <h2>Prayer times and location</h2>
    <p>
        Prayer times are worked out in the browser from the location the browser
        reports, and only if the person allows it. The location is not sent to this
        server and is not stored.
    </p>

    <h2>Who can see what</h2>
    <p>
        Access is set per person. Salary and employee files are limited to HR and
        the owner; a colleague cannot open another colleague's pay. Every company on
        this system is kept separate — nobody can see another company's records.
    </p>

    <h2>What is written down about what people do</h2>
    <p>
        Actions that change or remove records are logged with who did them and when:
        deleting a user, a lead or a deal, exporting a list, verifying somebody,
        opening another person's payslip, and resetting a password. This exists so
        that if something goes missing it can be asked about.
    </p>

    <h2>Deleting</h2>
    <p>
        Deleting a person, a lead or a deal sets it aside in the Recycle Bin rather
        than destroying it, so a mistake can be undone and the attendance and pay
        records attached to it survive. Only the owner can destroy something
        permanently, and that is recorded too.
    </p>

    <h2>Backups</h2>
    <p>
        A copy of the database is written every night and kept for two weeks. Backups
        are stored on the same server, outside the part of it the web can reach.
    </p>

    <h2>Asking about your own record</h2>
    <p>
        Anyone who works here can see their own attendance, leave, pay and payslips
        from inside the CRM. For anything else — a copy of your record, a correction,
        or its removal — ask whoever administers this CRM at your company.
    </p>
@endsection
