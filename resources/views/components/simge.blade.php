@props(['ad', 'etiket' => null])
{!! \App\Support\Simgeler::svg($ad, $attributes->get('class'), $etiket) !!}
