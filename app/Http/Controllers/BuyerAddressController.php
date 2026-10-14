<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BuyerAddressController extends Controller
{
    public function create(Request $request): View
    {
        return view('buyer.delivery-address', [
            'address' => $request->user()->addresses()->where('is_default', true)->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $request->user()->addresses()->update(['is_default' => false]);

            Address::create([
                'user_id' => $request->user()->id,
                'label' => 'Delivery address',
                ...$data,
                'is_default' => true,
            ]);
        });

        return redirect()->route('cart')->with('status', 'Your delivery address was saved. You can now place a COD order.');
    }
}
