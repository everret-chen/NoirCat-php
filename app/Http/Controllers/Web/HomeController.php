<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PostService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly PostService $posts)
    {
    }

    /**
     * Landing page: module overview plus the newest forum activity.
     */
    public function index(): View
    {
        $latest = $this->posts->paginate(['sort' => 'latest'], 5);

        return view('home', [
            'posts' => $latest->items(),
        ]);
    }
}
