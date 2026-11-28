                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td style="background-color:#0f172a;border-radius:0 0 16px 16px;padding:24px 32px;text-align:center;border:1px solid #1e293b;border-top:none;">
                        <p style="margin:0 0 16px;font-size:13px;color:#94a3b8;line-height:1.6;">
                            Get the MentorKhoj app:
                            <a href="{{ $brand['play_store_url'] ?? 'https://play.google.com/store/apps/details?id=com.betterwitssoftware.mentorkhoj' }}" style="color:#5eead4;text-decoration:none;">Android</a>
                            &middot;
                            <a href="{{ $brand['app_store_url'] ?? 'https://apps.apple.com/in/app/mentorkhoj/id6739975565' }}" style="color:#5eead4;text-decoration:none;">iPhone</a>
                            <br>
                            Call <a href="tel:{{ $brand['support_call_tel'] ?? '+919102695888' }}" style="color:#5eead4;text-decoration:none;">{{ $brand['support_call_display'] ?? '+91 91026 95888' }}</a>
                            &middot; WhatsApp <a href="{{ $brand['support_whatsapp_url'] ?? 'https://wa.me/917366939888' }}" style="color:#5eead4;text-decoration:none;">{{ $brand['support_whatsapp_display'] ?? '+91 73669 39888' }}</a>
                        </p>
                        <p style="margin:0 0 12px;font-size:13px;color:#94a3b8;line-height:1.6;">
                            Questions? Reply to this email or write to
                            <a href="mailto:{{ $brand['admin_email'] ?? 'admin@mentorkhoj.com' }}" style="color:#5eead4;text-decoration:none;">{{ $brand['admin_email'] ?? 'admin@mentorkhoj.com' }}</a>
                        </p>
                        <p style="margin:0 0 16px;">
                            <a href="{{ $brand['site_url'] ?? 'https://www.mentorkhoj.com' }}" style="color:#94a3b8;text-decoration:none;font-size:12px;margin:0 8px;">Home</a>
                            <span style="color:#475569;">·</span>
                            <a href="{{ $brand['mentors_url'] ?? 'https://www.mentorkhoj.com/mentors' }}" style="color:#94a3b8;text-decoration:none;font-size:12px;margin:0 8px;">Mentors</a>
                            <span style="color:#475569;">·</span>
                            <a href="{{ $brand['seminars_url'] ?? 'https://www.mentorkhoj.com/seminars' }}" style="color:#94a3b8;text-decoration:none;font-size:12px;margin:0 8px;">Seminars</a>
                            <span style="color:#475569;">·</span>
                            <a href="{{ $brand['internships_url'] ?? 'https://www.mentorkhoj.com/internships' }}" style="color:#94a3b8;text-decoration:none;font-size:12px;margin:0 8px;">Internships</a>
                        </p>
                        <p style="margin:0;font-size:11px;color:#64748b;">
                            © {{ date('Y') }} {{ $brand['site_name'] ?? 'MentorKhoj' }}. All rights reserved.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
