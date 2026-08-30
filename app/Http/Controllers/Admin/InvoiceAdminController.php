<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\FormMailLogic;
use App\CentralLogics\InvoiceCalculationLogic;
use App\CentralLogics\InvoiceCompanyProfile;
use App\CentralLogics\InvoiceNumberLogic;
use App\CentralLogics\InvoicePdfLogic;
use App\CentralLogics\InvoicePrefillLogic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Mail\InvoiceSentMail;
use App\Model\Invoice\Invoice;
use App\Model\Invoice\InvoiceSetting;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InvoiceAdminController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'total_invoices' => Invoice::count(),
            'total_revenue' => (float) Invoice::whereIn('payment_status', ['paid', 'partially_paid'])->sum('total_amount'),
            'paid' => Invoice::where('payment_status', 'paid')->count(),
            'pending' => Invoice::where('payment_status', 'pending')->count(),
            'partially_paid' => Invoice::where('payment_status', 'partially_paid')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
            'outstanding' => (float) Invoice::whereIn('payment_status', ['pending', 'partially_paid'])->sum('balance_due'),
        ];

        $recent = Invoice::with('createdBy')->latest()->limit(10)->get();

        return view('admin-views.invoices.dashboard', compact('stats', 'recent'));
    }

    public function index(Request $request): View
    {
        $query = Invoice::with('createdBy')->latest();

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($from = $request->get('from_date')) {
            $query->whereDate('invoice_date', '>=', $from);
        }

        if ($to = $request->get('to_date')) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        if ($min = $request->get('min_amount')) {
            $query->where('total_amount', '>=', (float) $min);
        }

        if ($max = $request->get('max_amount')) {
            $query->where('total_amount', '<=', (float) $max);
        }

        $sort = $request->get('sort', 'latest');
        if ($sort === 'oldest') {
            $query->reorder('created_at', 'asc');
        } elseif ($sort === 'amount_desc') {
            $query->reorder('total_amount', 'desc');
        } elseif ($sort === 'amount_asc') {
            $query->reorder('total_amount', 'asc');
        }

        $invoices = $query->paginate(25)->appends($request->query());

        return view('admin-views.invoices.index', compact('invoices'));
    }

    public function create(Request $request): View
    {
        $settings = InvoiceSetting::instance();
        $company = InvoiceCompanyProfile::locked();
        $prefill = [];

        if ($request->filled('order_id')) {
            $prefill = InvoicePrefillLogic::fromOrder((int) $request->get('order_id'));
        } elseif ($request->filled('booking_id')) {
            $prefill = InvoicePrefillLogic::fromBooking((int) $request->get('booking_id'));
        } elseif ($request->filled('user_id')) {
            $prefill = InvoicePrefillLogic::fromUser((int) $request->get('user_id'));
        }

        return view('admin-views.invoices.create', [
            'settings' => $settings,
            'company' => $company,
            'prefill' => $prefill,
            'nextInvoiceNumber' => InvoiceNumberLogic::previewNext($settings),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse|JsonResponse
    {
        $invoice = $this->persistInvoice(new Invoice(), $request);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'invoice' => $invoice->load('items')]);
        }

        return redirect()->route('admin.invoices.show', $invoice->id)
            ->with('success', translate('Invoice saved successfully'));
    }

    public function show(int $id): View
    {
        $invoice = Invoice::with(['items', 'createdBy'])->findOrFail($id);
        $company = InvoiceCompanyProfile::mergedWithSettings();
        $pdfData = InvoicePdfLogic::viewData($invoice);

        return view('admin-views.invoices.show', compact('invoice', 'company', 'pdfData'));
    }

    public function edit(int $id): View|RedirectResponse
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        if (!$invoice->isEditable()) {
            return redirect()->route('admin.invoices.show', $id)
                ->with('error', translate('This invoice cannot be edited'));
        }

        $settings = InvoiceSetting::instance();
        $company = InvoiceCompanyProfile::locked();

        return view('admin-views.invoices.edit', compact('invoice', 'settings', 'company'));
    }

    public function update(UpdateInvoiceRequest $request, int $id): RedirectResponse|JsonResponse
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        if (!$invoice->isEditable()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Invoice not editable'], 422);
            }

            return back()->with('error', translate('This invoice cannot be edited'));
        }

        $invoice = $this->persistInvoice($invoice, $request);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'invoice' => $invoice->load('items')]);
        }

        return redirect()->route('admin.invoices.show', $invoice->id)
            ->with('success', translate('Invoice updated successfully'));
    }

    public function duplicate(int $id): RedirectResponse
    {
        $source = Invoice::with('items')->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $invoice = $source->replicate(['invoice_number', 'deleted_at']);
            $invoice->invoice_number = InvoiceNumberLogic::assign();
            $invoice->status = 'draft';
            $invoice->invoice_number_manual = false;
            $invoice->created_by_admin_id = auth('admin')->id();
            $invoice->updated_by_admin_id = auth('admin')->id();
            $invoice->save();

            foreach ($source->items as $item) {
                $newItem = $item->replicate();
                $newItem->invoice_id = $invoice->id;
                $newItem->save();
            }

            return $invoice;
        });

        return redirect()->route('admin.invoices.edit', $copy->id)
            ->with('success', translate('Invoice duplicated as draft'));
    }

    public function cancel(int $id): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'cancelled';
        $invoice->payment_status = 'cancelled';
        $invoice->updated_by_admin_id = auth('admin')->id();
        $invoice->save();

        return back()->with('success', translate('Invoice cancelled'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        if ((int) $admin->admin_role_id !== 1) {
            return back()->with('error', translate('Only super admin can delete invoices'));
        }

        Invoice::findOrFail($id)->delete();

        return redirect()->route('admin.invoices.list')->with('success', translate('Invoice deleted'));
    }

    public function pdf(int $id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);

        return InvoicePdfLogic::download($invoice);
    }

    public function print(int $id): View
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        $pdfData = InvoicePdfLogic::viewData($invoice);

        return view('admin-views.invoices.print', $pdfData);
    }

    public function send(int $id): RedirectResponse
    {
        $invoice = Invoice::with('items')->findOrFail($id);

        if (!FormMailLogic::isMailEnabled()) {
            return back()->with('error', translate('Mail is not configured'));
        }

        if (!$invoice->customer_email) {
            return back()->with('error', translate('Customer email is required to send invoice'));
        }

        Mail::to($invoice->customer_email)->send(new InvoiceSentMail($invoice));

        if ($invoice->status === 'draft' || $invoice->status === 'generated') {
            $invoice->status = 'sent';
            $invoice->save();
        }

        return back()->with('success', translate('Invoice sent successfully'));
    }

    public function prefillOrder(int $orderId): JsonResponse
    {
        return response()->json(InvoicePrefillLogic::fromOrder($orderId));
    }

    public function prefillBooking(int $bookingId): JsonResponse
    {
        return response()->json(InvoicePrefillLogic::fromBooking($bookingId));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('f_name', 'like', "%{$q}%")
                    ->orWhere('l_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'f_name', 'l_name', 'email', 'phone']);

        return response()->json($users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim($user->f_name . ' ' . $user->l_name),
                'email' => $user->email,
                'phone' => $user->phone,
            ];
        }));
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'tax_mode' => 'required|in:none,gst,cgst_sgst,igst,custom',
            'place_of_supply' => 'nullable|string|max:120',
            'additional_charges' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
        ]);

        $result = InvoiceCalculationLogic::calculate($validated['items'], $validated);

        return response()->json($result);
    }

    private function persistInvoice(Invoice $invoice, StoreInvoiceRequest $request): Invoice
    {
        $data = $request->validated();
        $action = $data['action'] ?? 'generate';
        $isNew = !$invoice->exists;

        $totals = InvoiceCalculationLogic::calculate($data['items'], [
            'tax_mode' => $data['tax_mode'],
            'place_of_supply' => $data['place_of_supply'] ?? InvoiceCompanyProfile::get('default_place_of_supply'),
            'additional_charges' => $data['additional_charges'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? 0,
        ]);

        if ($totals['amount_paid'] > $totals['total_amount'] + 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount_paid' => ['Amount paid cannot exceed invoice total.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $data, $action, $isNew, $totals, $request) {
            if ($isNew || empty($invoice->invoice_number)) {
                $manual = !empty($data['invoice_number_manual']) && !empty($data['invoice_number']);
                $invoice->invoice_number = InvoiceNumberLogic::assign($data['invoice_number'] ?? null, $manual);
                $invoice->invoice_number_manual = $manual;
            } elseif (!empty($data['invoice_number_manual']) && !empty($data['invoice_number']) && $data['invoice_number'] !== $invoice->invoice_number) {
                $invoice->invoice_number = InvoiceNumberLogic::assign($data['invoice_number'], true);
                $invoice->invoice_number_manual = true;
            }

            $shippingAddress = $data['shipping_address'] ?? null;
            if (empty($shippingAddress) || $shippingAddress === ($data['billing_address'] ?? null)) {
                $shippingAddress = $data['billing_address'] ?? null;
                $data['shipping_city'] = $data['billing_city'] ?? null;
                $data['shipping_state'] = $data['billing_state'] ?? null;
                $data['shipping_country'] = $data['billing_country'] ?? null;
                $data['shipping_postal_code'] = $data['billing_postal_code'] ?? null;
            }

            $invoice->fill([
                'status' => $action === 'draft' ? 'draft' : 'generated',
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'payment_date' => $data['payment_date'] ?? null,
                'currency' => $data['currency'],
                'place_of_supply' => $data['place_of_supply'] ?? InvoiceCompanyProfile::get('default_place_of_supply'),
                'reference_number' => $data['reference_number'] ?? null,
                'tax_mode' => $data['tax_mode'],
                'user_id' => $data['user_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_type' => $data['customer_type'] ?? null,
                'customer_company' => $data['customer_company'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'billing_city' => $data['billing_city'] ?? null,
                'billing_state' => $data['billing_state'] ?? null,
                'billing_country' => $data['billing_country'] ?? null,
                'billing_postal_code' => $data['billing_postal_code'] ?? null,
                'shipping_address' => $shippingAddress,
                'shipping_city' => $data['shipping_city'] ?? null,
                'shipping_state' => $data['shipping_state'] ?? null,
                'shipping_country' => $data['shipping_country'] ?? null,
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'customer_gstin' => isset($data['customer_gstin']) ? strtoupper(trim($data['customer_gstin'])) : null,
                'customer_pan' => $data['customer_pan'] ?? null,
                'customer_external_id' => $data['customer_external_id'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'taxable_amount' => $totals['taxable_amount'],
                'cgst' => $totals['cgst'],
                'sgst' => $totals['sgst'],
                'igst' => $totals['igst'],
                'other_tax' => $totals['other_tax'],
                'additional_charges' => $totals['additional_charges'],
                'round_off' => $totals['round_off'],
                'total_amount' => $totals['total_amount'],
                'amount_paid' => $totals['amount_paid'],
                'balance_due' => $totals['balance_due'],
                'payment_status' => $data['payment_status'],
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'customer_notes' => strip_tags($data['customer_notes'] ?? ''),
                'terms' => strip_tags($data['terms'] ?? ''),
            ]);

            if ($isNew) {
                $invoice->created_by_admin_id = auth('admin')->id();
            }
            $invoice->updated_by_admin_id = auth('admin')->id();
            $invoice->save();

            $invoice->items()->delete();
            foreach ($totals['items'] as $item) {
                $invoice->items()->create([
                    'sort_order' => $item['sort_order'],
                    'service_name' => $item['service_name'],
                    'description' => $item['description'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'discount_type' => $item['discount_type'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_type' => $item['tax_type'] ?? null,
                    'tax_amount' => $item['tax_amount'],
                    'line_total' => $item['line_total'],
                ]);
            }

            if ($data['payment_status'] === 'paid' && $action !== 'draft') {
                $invoice->status = 'paid';
            } elseif ($data['payment_status'] === 'partially_paid') {
                $invoice->status = 'partially_paid';
            }

            $invoice->save();

            return $invoice->fresh(['items']);
        });
    }
}
