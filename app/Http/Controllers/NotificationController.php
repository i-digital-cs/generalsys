<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * In-app notification inbox (database channel).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(25),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $this->findOwned($request, $notification);
        $item->markAsRead();

        $url = $item->data['url'] ?? route('dashboard');

        return redirect()->to($url);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()
            ->back()
            ->with('success', __('notifications.flash.all_read'));
    }

    private function findOwned(Request $request, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $item */
        $item = $request->user()->notifications()->where('id', $id)->firstOrFail();

        return $item;
    }
}
