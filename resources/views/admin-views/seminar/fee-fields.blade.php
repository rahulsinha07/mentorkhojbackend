<div class="card mt-3">
  <div class="card-header">
    <h5 class="mb-0">Seminar fee</h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="input-label" for="fee_amount">Fee amount (₹)</label>
        <input
          type="number"
          name="fee_amount"
          id="fee_amount"
          class="form-control"
          min="0"
          step="0.01"
          value="{{ old('fee_amount', $seminar?->fee_amount ?? 0) }}"
        />
        <small class="text-muted">Use <strong>0</strong> for free seminars. Any amount above 0 requires login + Razorpay payment.</small>
      </div>
      <div class="col-md-6">
        <label class="input-label" for="currency">Currency</label>
        <select name="currency" id="currency" class="form-control">
          @foreach (['INR', 'USD'] as $code)
            <option value="{{ $code }}" {{ old('currency', $seminar?->currency ?? 'INR') === $code ? 'selected' : '' }}>
              {{ $code }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</div>
