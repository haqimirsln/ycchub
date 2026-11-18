<x-mail::message>
# Hi {{ $name }},

Thanks for signing up for YCC Hub. An MT member reviewed your sign-up and **couldn't approve it** this time.

@if ($note)
<x-mail::panel>
**Note from the MT**

{{ $note }}
</x-mail::panel>
@endif

If you think this is a mistake, message the club on [Instagram]({{ $instagramUrl }}) with your matric number and we'll sort it out. You're also welcome to sign up again with the right details.

Thank you,<br>
The YCC MT
</x-mail::message>
