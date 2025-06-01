<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lead;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Filament\Resources\InvoiceResource;

class InvoiceController extends Controller // Renamed from InvoicePdfController
{
    public function download(Invoice $invoice)
    {
        // Ensure items are loaded
        $invoice->load('items', 'lead');

        // Determine which template to use based on $invoice->template_id
        $templateName = $invoice->template_id ?? 'default'; // Default to 'default' if not set
        $templateView = 'invoices.pdf.' . $templateName;

        // Fallback to default if the specific template view doesn't exist
        if (!view()->exists($templateView)) {
            $templateView = 'invoices.pdf.default';
        }

        $pdf = Pdf::loadView($templateView, compact('invoice'));

        // Stream the PDF to the browser for preview instead of direct download
        return $pdf->stream('invoice-' . $invoice->invoice_number . '.pdf');
    }

    public function preview(Invoice $invoice)
    {
        // Ensure items are loaded
        $invoice->load('items', 'lead');

        // Determine which template to use based on $invoice->template_id
        $templateName = $invoice->template_id ?? 'default'; // Default to 'default' if not set
        // Ensure templateName is a string and corresponds to a file, e.g., 'modern', 'classic', 'compact'
        // If template_id could be numeric or other, map it to a valid string name here.
        $templateView = 'invoices.pdf.' . $templateName;

        // Fallback to default if the specific template view doesn't exist
        if (!view()->exists($templateView)) {
            // Log this occurrence or handle as an error if specific templates are mandatory
            $templateView = 'invoices.pdf.default'; // Assuming you have a default.blade.php
        }

        // Return the HTML view directly
        return view($templateView, compact('invoice'));
    }

    public function createFromLeadAndEdit(Request $request, Lead $lead)
    {
        $user = Auth::user();
        if (!$user) {
            // Handle unauthenticated user, perhaps redirect to login
            return redirect()->route('filament.admin.auth.login');
        }

        // Create a new Invoice instance
        $invoice = new Invoice();
        $invoice->lead_id = $lead->id;
        $invoice->user_id = $user->id;
        $invoice->invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5)); // Generate a unique invoice number
        $invoice->invoice_date = now();
        $invoice->due_date = now()->addDays(30); // Default due date, e.g., 30 days from now
        $invoice->status = 'draft'; // Default status
        $invoice->template_id = 'default'; // Default template ID to string 'default'
        // Initialize totals - these will be recalculated based on items
        $invoice->subtotal = 0;
        $invoice->tax_rate = 0; // Default tax rate
        $invoice->tax_amount = 0;
        $invoice->total_amount = 0;
        $invoice->save(); // Save the initial invoice to get an ID

        // Prepare invoice items from leadAmounts
        $invoiceItemsData = [];
        $subtotal = 0;
        if ($lead->leadAmounts->isNotEmpty()) {
            foreach ($lead->leadAmounts as $leadAmount) {
                $quantity = 1;
                $unitPrice = $leadAmount->amount_cleared ?? 0;
                $totalPrice = round($quantity * $unitPrice, 2);
                $invoiceItemsData[] = [
                    'invoice_id' => $invoice->id,
                    'description' => $leadAmount->description ?? 'Payment related to Lead #' . $lead->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $subtotal += $totalPrice;
            }
            // Bulk insert items for efficiency if your InvoiceItem model supports it
            // Otherwise, loop and create
            if (!empty($invoiceItemsData)) {
                $invoice->items()->createMany($invoiceItemsData);
            }
        }

        // Update invoice totals based on items
        $taxRate = $invoice->tax_rate ?? 0;
        $taxAmount = ($subtotal * $taxRate) / 100;
        $totalAmount = $subtotal + $taxAmount;

        $invoice->subtotal = round($subtotal, 2);
        $invoice->tax_amount = round($taxAmount, 2);
        $invoice->total_amount = round($totalAmount, 2);
        $invoice->save();

        // Redirect to the Filament edit page for this newly created invoice
        return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
    }
}

