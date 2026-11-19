<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::all();
        return view('admin.emails.templates', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        EmailTemplate::create($request->all());

        return redirect()->back()->with('success', 'Email Template created successfully.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
        ]);

        $template = EmailTemplate::findOrFail($id);
        $template->update($request->all());

        return redirect()->back()->with('success', 'Email Template updated successfully.');
    }

    public function destroy($id)
    {
        EmailTemplate::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Email Template deleted successfully.');
    }

    public function getAll()
    {
        return response()->json(EmailTemplate::all());
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
        ]);

        $path = $request->file('image')->store('email_images', 'public');

        return response()->json(['url' => \Illuminate\Support\Facades\Storage::url($path)]);
    }
}
