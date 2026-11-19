<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    public function showSignPage($type, $id)
    {
        // For security, you might want a hash/token instead of just ID.
        // Keeping it simple based on the plan.
        $documentName = ucfirst($type) . ' #' . $id;
        
        return view('signature.sign_page', compact('type', 'id', 'documentName'));
    }

    public function saveSignature(Request $request, $type, $id)
    {
        $request->validate([
            'signature_data' => 'required|string', // Data URL from canvas
            'signer_name' => 'required|string|max:255',
        ]);

        // Process Base64 image
        $image_parts = explode(";base64,", $request->signature_data);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = base64_decode($image_parts[1]);
        $fileName = 'signatures/' . uniqid() . '.' . $image_type;

        Storage::disk('public')->put($fileName, $image_base64);

        $signature = Signature::create([
            'document_type' => $type,
            'document_id' => $id,
            'signed_by_name' => $request->signer_name,
            'signed_by_ip' => $request->ip(),
            'signature_image_path' => $fileName,
            'signed_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Document signed successfully.']);
    }
}
