@php($prefix = $prefix ?? '')
@php($regional = $regional ?? false)
@php($field = fn (string $name) => $prefix === '' ? $name : $prefix.'_'.$name)
@php($address = $address ?? null)
<div data-address-picker data-locations='@json(\App\Support\PhilippineLocations::all())' data-regions='@json(\App\Support\PhilippineLocations::regions())' data-initial-province="{{ old($field('province'), $address?->province) }}" data-initial-city="{{ old($field('city'), $address?->city) }}" class="grid gap-3 sm:grid-cols-2">
    <input class="field sm:col-span-2" name="{{ $field('line1') }}" value="{{ old($field('line1'), $address?->line1) }}" placeholder="Street, building, or unit" required>
    @if($regional)<select class="field" name="{{ $field('region') }}" data-region required><option value="">Choose a region</option>@foreach(\App\Support\PhilippineLocations::regions() as $region => $provinces)<option value="{{ $region }}" @selected(old($field('region')) === $region)>{{ $region }}</option>@endforeach</select>@endif
    <select class="field" name="{{ $field('province') }}" data-province required><option value="">Choose a province</option>@foreach(\App\Support\PhilippineLocations::all() as $province => $cities)<option value="{{ $province }}" @selected(old($field('province'), $address?->province) === $province)>{{ $province }}</option>@endforeach</select>
    <select class="field" name="{{ $field('city') }}" data-city required disabled><option value="">Choose a province first</option></select>
    <input class="field" name="{{ $field('barangay') }}" value="{{ old($field('barangay'), $address?->barangay) }}" placeholder="Barangay" required>
    <input class="field" name="{{ $field('postal_code') }}" value="{{ old($field('postal_code'), $address?->postal_code) }}" placeholder="Postal code" required>
</div>
