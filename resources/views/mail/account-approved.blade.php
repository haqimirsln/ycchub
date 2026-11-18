<x-mail::message>
# Welcome aboard, {{ $name }}!

Your Young Conservationist Club membership is **approved**. You can now register for events, check in, and track your own participation in YCC Hub.

<x-mail::button :url="$portalUrl">
Open YCC Hub
</x-mail::button>

<x-mail::panel>
**Your first three steps**

1. **Log in** with your matric number ({{ $matric }}) and the password you chose.
2. **Put it on your phone.** [Here's how]({{ $appUrl }}), it takes 20 seconds and needs no app store.
3. **Join an event.** Register in one tap, then scan the QR code on the day to check in.
</x-mail::panel>

Your status starts as **New member** and becomes **Active** after your first event.

See you out there,<br>
The YCC MT

<x-slot:subcopy>
Button not working? Copy this link into your browser: <span class="break-all">[{{ $portalUrl }}]({{ $portalUrl }})</span>
</x-slot:subcopy>
</x-mail::message>
