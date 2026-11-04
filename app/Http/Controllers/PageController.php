<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PageController extends Controller
{

    /**
     * @return Application|Factory|View
     */
    public function get_privacy_policy(): Factory|View|Application
    {
        return view('page.privacy-policy');
    }

    /**
     * @return Application|Factory|View
     */
    public function get_about_us(): Factory|View|Application
    {
        return view('page.about-us');
    }

    /**
     * Isolated public profile for Arvind Pandey IPS Bihar (MentorKhoj / health angle).
     * Does not use admin layout, CMS, or API.
     */
    public function get_arvind_pandey_ips_bihar(): Factory|View|Application
    {
        return view('page.arvind-pandey-ips-bihar');
    }

}
