                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td style="background-color:#0f172a;border-radius:0 0 16px 16px;padding:24px 32px;text-align:center;border:1px solid #1e293b;border-top:none;">
                        <p style="margin:0 0 12px;font-size:13px;color:#94a3b8;line-height:1.6;">
                            Questions? Reply to this email or write to
                            <a href="mailto:{{ $brand['admin_email'] ?? 'mentorkhoj@gmail.com' }}" style="color:#5eead4;text-decoration:none;">{{ $brand['admin_email'] ?? 'mentorkhoj@gmail.com' }}</a>
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
