<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BuyerNotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(30);
        $request->user()->unreadNotifications->markAsRead();
        return view('buyer.notifications.index', compact('notifications'));
    }
}
