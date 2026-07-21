<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #0f172a; line-height: 1.6;">
  <h2>You're registered — {{ $seminar->title }}</h2>
  <p>Hi {{ $booking->name }},</p>
  <p>Your seat for <strong>{{ $seminar->title }}</strong> is confirmed.</p>

  <p><strong>Booking reference:</strong> {{ $booking->booking_ref }}</p>
  <p><strong>Date:</strong> {{ $seminar->date }}</p>
  <p><strong>Mode:</strong> {{ $seminar->mode }}</p>

  @if($paid)
  <div style="margin: 20px 0; padding: 16px; background: #ecfdf5; border-radius: 8px;">
    <p style="margin: 0;"><strong>Payment confirmed:</strong> ₹{{ number_format($booking->amount, 0) }}</p>
    <p style="margin: 8px 0 0;">Razorpay payment ID: {{ $booking->razorpay_payment_id }}</p>
  </div>
  @else
  <p style="color: #059669;"><strong>100% FREE</strong> — no payment required.</p>
  @endif

  <p>We'll share joining or venue details before the session.</p>
  <p>— MentorKhoj Team</p>
</body>
</html>
