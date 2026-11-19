<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Deal;
use App\Models\Lead;
use App\Mail\DynamicProposalMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function inbox()
    {
        $emails = Email::orderBy('created_at', 'desc')->paginate(50);
        return view('admin.emails.inbox', compact('emails'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'to_address' => 'required|email',
            'subject' => 'required|string',
            'body_html' => 'required|string',
            'deal_id' => 'nullable|exists:deals,id',
            'lead_id' => 'nullable|exists:leads,id',
        ]);

        // The log and array mailers write the message to a file or memory; nothing
        // leaves the server. Saying "sent" in that case hides a mail setup that was
        // never finished, so the record and the message both tell the truth instead.
        $delivers = !in_array(config('mail.default'), ['log', 'array'], true);

        try {
            Mail::to($request->to_address)->send(new DynamicProposalMail($request->subject, $request->body_html));

            Email::create([
                'deal_id' => $request->deal_id,
                'lead_id' => $request->lead_id,
                'direction' => 'outbound',
                'from_address' => config('mail.from.address', 'sales@systematicitsolutions.com'),
                'to_address' => $request->to_address,
                'subject' => $request->subject,
                'body_html' => $request->body_html,
                'status' => $delivers ? 'sent' : 'not_delivered'
            ]);

            return redirect()->back()->with(
                'success',
                $delivers
                    ? 'Email sent successfully.'
                    : 'Saved to the log only. No mail server is configured (MAIL_MAILER=' . config('mail.default') . '), so nothing was delivered.'
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    public function getThread($type, $id)
    {
        $query = Email::query();
        if ($type === 'deal') {
            $query->where('deal_id', $id);
        } elseif ($type === 'lead') {
            $query->where('lead_id', $id);
        } else {
            return response()->json([]);
        }

        $emails = $query->orderBy('created_at', 'asc')->get();
        return response()->json($emails);
    }
}
