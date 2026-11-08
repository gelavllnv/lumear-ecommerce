<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background:#f4e8db;font-family:Arial,sans-serif;color:#292323">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:30px 14px;background:#f4e8db">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#f4e8db;border:1px solid #d6bfa8;border-radius:18px;overflow:hidden">
                    <tr>
                        <td style="height:6px;background:#c9a36a;font-size:0;line-height:0">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:26px 34px;background:#42101f">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td valign="middle">
                                        <img src="cid:lumear-logo@lumear" alt="LUMEAR" width="50" height="50" style="display:block;width:50px;height:50px;object-fit:contain">
                                    </td>
                                    <td valign="middle" style="padding-left:14px;color:#fffdf9">
                                        <div style="font-size:23px;font-weight:bold;letter-spacing:.4px">LUMEAR</div>
                                        <div style="margin-top:4px;color:#f4e8db;font-size:10px;font-weight:bold;letter-spacing:1.35px">LOGISTICS ACCOUNT SECURITY</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 34px 30px">
                            <div style="display:inline-block;padding:6px 10px;border-radius:20px;background:#ead6c0;color:#42101f;font-size:10px;font-weight:bold;letter-spacing:.8px">PASSWORD RESET REQUEST</div>
                            <h1 style="margin:17px 0 10px;color:#42101f;font-size:28px;line-height:1.2">Reset your password</h1>
                            <p style="margin:0;color:#5c4b46;font-size:15px;line-height:1.65">Hello {{ $name }}, we received a request to reset the password for your LUMEAR Logistics account.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0">
                                <tr>
                                    <td style="border-radius:9px;background:#42101f">
                                        <a href="{{ $url }}" style="display:inline-block;padding:15px 23px;border-radius:9px;color:#fffdf9;font-size:14px;font-weight:bold;text-decoration:none">Reset Password</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 20px;color:#5c4b46;font-size:13px;line-height:1.6">This password-reset link will expire in {{ $expiresIn }} minutes.</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-left:4px solid #c9a36a;background:#ead6c0">
                                <tr>
                                    <td style="padding:13px 15px;color:#5c4b46;font-size:12px;line-height:1.6"><strong style="color:#42101f">Security reminder:</strong> If you did not request a password reset, no action is needed. Your current password remains unchanged.</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:19px 34px;background:#292323;color:#f4e8db;font-size:11px;line-height:1.5">This is an automated message from LUMEAR Logistics. Please do not reply to this email.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
