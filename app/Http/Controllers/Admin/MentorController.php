<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\MentorImageService;
use App\CentralLogics\MentorLogic;
use App\Http\Controllers\Controller;
use App\Model\Category;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MentorController extends Controller
{
    public function __construct(
        private Mentor $mentor,
        private Category $category,
    ) {}

    public function list(Request $request): View|Factory|Application
    {
        $queryParam = [];
        $search = $request->get('search');

        $query = $this->mentor->withCount(['services', 'bookings'])->latest();
        if ($request->has('search') && $search) {
            $key = explode(' ', $search);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('display_name', 'like', "%{$value}%")
                        ->orWhere('username', 'like', "%{$value}%")
                        ->orWhere('headline', 'like', "%{$value}%");
                }
            });
            $queryParam = ['search' => $search];
        }

        $mentors = $query->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.mentor.list', compact('mentors', 'search'));
    }

    public function edit($id): View|Factory|Application
    {
        $mentor = $this->mentor->with('services')->findOrFail($id);
        $categories = $this->category->where('parent_id', 0)->orderBy('name')->get();
        [$categoryId, $subCategoryId] = $this->resolveCategoryIds($mentor);
        $subCategories = $categoryId
            ? $this->category->where('parent_id', $categoryId)->orderBy('name')->get()
            : collect();
        $social = $mentor->social_links_array;

        return view('admin-views.mentor.edit', compact(
            'mentor',
            'categories',
            'categoryId',
            'subCategoryId',
            'subCategories',
            'social',
        ));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $mentor = $this->mentor->with('services')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'display_name' => 'required|string|max:255',
            'username' => 'required|string|max:100|regex:/^[a-z0-9-]+$/|unique:mentors,username,' . $mentor->id,
            'headline' => 'nullable|string|max:500',
            'bio_html' => 'nullable|string',
            'category_id' => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'status' => 'required|in:active,draft',
            'profile_discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percent,amount',
            'share_caption' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'string|max:255',
            'social_links.instagram' => 'nullable|url|max:500',
            'social_links.facebook' => 'nullable|url|max:500',
            'social_links.linkedin' => 'nullable|url|max:500',
            'social_links.youtube' => 'nullable|url|max:500',
            'social_links.whatsapp' => 'nullable|url|max:500',
            'social_links.linktree' => 'nullable|url|max:500',
            'social_links.website' => 'nullable|url|max:500',
            'delete_service_ids' => 'nullable|array',
            'delete_service_ids.*' => 'integer|exists:mentor_services,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $mentor->display_name = $request->display_name;
        $mentor->username = MentorLogic::slugifyUsername($request->username);
        $mentor->headline = $request->headline;
        $mentor->bio_html = $request->bio_html;
        $mentor->status = $request->status;
        $mentor->is_published = $request->boolean('is_published');
        if ($mentor->is_published) {
            $mentor->status = 'active';
        }
        $mentor->profile_discount = (float) ($request->profile_discount ?? 0);
        $mentor->discount_type = $request->discount_type ?: 'percent';
        $mentor->share_caption = $request->share_caption;

        $category = [];
        if ($request->category_id) {
            $category[] = ['id' => (string) $request->category_id, 'position' => 1];
        }
        if ($request->sub_category_id) {
            $category[] = ['id' => (string) $request->sub_category_id, 'position' => 2];
        }
        $mentor->category_ids = json_encode($category);

        $mentor->social_links = json_encode(
            MentorLogic::normalizeSocialLinks($request->input('social_links', []))
        );

        try {
            $updatedImages = MentorImageService::applyImageUpdate(
                $mentor,
                $request->input('remove_images', []),
                $request->file('images') ?? [],
            );
            if ($updatedImages !== null) {
                $mentor->images = $updatedImages;
            }
        } catch (\RuntimeException $e) {
            Toastr::error($e->getMessage());
            return back()->withInput();
        }

        $mentor->save();

        $this->syncServices($mentor, $request);

        Toastr::success(translate('mentor updated successfully'));
        return redirect()->route('admin.mentor.edit', $mentor->id);
    }

    public function status(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        $mentor->status = $request->status ? 'active' : 'draft';
        $mentor->save();

        Toastr::success(translate('mentor status updated!'));
        return back();
    }

    public function publish(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        $mentor->is_published = (bool) $request->is_published;
        if ($mentor->is_published) {
            $mentor->status = 'active';
        }
        $mentor->save();

        Toastr::success(translate('mentor publish status updated!'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $mentor = $this->mentor->find($request->id);
        if (!$mentor) {
            Toastr::error(translate('mentor not found'));
            return back();
        }

        foreach ($mentor->images_array as $image) {
            if ($image && Storage::disk('public')->exists('product/' . $image)) {
                Storage::disk('public')->delete('product/' . $image);
            }
        }

        $mentor->services()->delete();
        $mentor->bookings()->delete();
        $mentor->earnings()->delete();
        $mentor->payouts()->delete();
        $mentor->settings()->delete();
        $mentor->shareLogs()->delete();
        $mentor->delete();

        Toastr::success(translate('mentor removed!'));
        return back();
    }

    /** @return array{0: int|string|null, 1: int|string|null} */
    private function resolveCategoryIds(Mentor $mentor): array
    {
        $cats = $mentor->category_ids_array;
        $parentId = null;
        $subId = null;

        foreach ($cats as $entry) {
            $pos = (int) ($entry['position'] ?? 0);
            $id = $entry['id'] ?? null;
            if ($id === null) {
                continue;
            }
            if ($pos <= 1 && $parentId === null) {
                $parentId = $id;
            } elseif ($pos >= 2) {
                $subId = $id;
            }
        }

        if ($parentId === null && isset($cats[0]['id'])) {
            $parentId = $cats[0]['id'];
        }
        if ($subId === null && isset($cats[1]['id'])) {
            $subId = $cats[1]['id'];
        }

        return [$parentId, $subId];
    }

    private function syncServices(Mentor $mentor, Request $request): void
    {
        $deleteIds = $request->input('delete_service_ids', []);
        if (!empty($deleteIds)) {
            MentorService::where('mentor_id', $mentor->id)
                ->whereIn('id', $deleteIds)
                ->delete();
        }

        foreach ($request->input('services', []) as $serviceId => $data) {
            if (!is_numeric($serviceId)) {
                continue;
            }
            $service = MentorService::where('mentor_id', $mentor->id)->where('id', (int) $serviceId)->first();
            if (!$service || empty($data['title'])) {
                continue;
            }
            $service->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'duration_minutes' => (int) ($data['duration_minutes'] ?? 30),
                'price' => (float) ($data['price'] ?? 0),
                'compare_at_price' => isset($data['compare_at_price']) && $data['compare_at_price'] !== ''
                    ? (float) $data['compare_at_price']
                    : null,
                'meeting_type' => $data['meeting_type'] ?? null,
                'is_enabled' => isset($data['is_enabled']),
                'is_popular' => isset($data['is_popular']),
            ]);
        }

        $maxOrder = (int) ($mentor->services()->max('sort_order') ?? 0);
        foreach ($request->input('new_services', []) as $data) {
            if (empty($data['title'])) {
                continue;
            }
            $maxOrder++;
            MentorService::create([
                'mentor_id' => $mentor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'duration_minutes' => (int) ($data['duration_minutes'] ?? 30),
                'price' => (float) ($data['price'] ?? 0),
                'compare_at_price' => isset($data['compare_at_price']) && $data['compare_at_price'] !== ''
                    ? (float) $data['compare_at_price']
                    : null,
                'meeting_type' => $data['meeting_type'] ?? 'video',
                'is_enabled' => true,
                'is_popular' => isset($data['is_popular']),
                'sort_order' => $maxOrder,
            ]);
        }
    }
}
