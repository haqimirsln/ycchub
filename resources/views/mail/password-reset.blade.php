<x-mail::message>
# Reset your password

Hi {{ $name }}, someone (hopefully you) asked to reset the password for your YCC Hub account.

<x-mail::button :url="$url">
Choose a new password
</x-mail::button>

This link works for {{ $minutes }} minutes. If you didn't ask for this, you can ignore this email and your password stays the same.

The YCC MT

<x-slot:subcopy>
Button not working? Copy this link into your browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>
