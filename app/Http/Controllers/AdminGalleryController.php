<?php

namespace App\Http\Controllers;

use App\Models\GalleryItem;
use App\Services\SupabaseStorage;
use Illuminate\Http\Request;
use Throwable;

class AdminGalleryController extends Controller
{
    public function index() { return view('admin.gallery', ['galleryItems' => GalleryItem::latest()->get()]); }
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['title'] = 'Gallery image';
        try {
            $data['image_path'] = app(SupabaseStorage::class)->upload($request->file('image'), 'gallery');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'The gallery image could not be uploaded. Please try again.');
        }
        GalleryItem::create($data);

        return back()->with('success', 'Gallery image added.');
    }

    public function update(Request $request, GalleryItem $gallery)
    {
        $data = $this->validated($request, false);
        if ($request->hasFile('image')) {
            try {
                $data['image_path'] = app(SupabaseStorage::class)->upload($request->file('image'), 'gallery');
            } catch (Throwable $exception) {
                report($exception);

                return back()->withInput()->with('error', 'The gallery image could not be uploaded. Please try again.');
            }
        }
        $oldImage = $gallery->image_path;
        $gallery->update($data);
        if (isset($data['image_path'])) {
            app(SupabaseStorage::class)->delete($oldImage);
        }

        return back()->with('success', 'Gallery item updated.');
    }
    public function destroy(GalleryItem $gallery) { app(SupabaseStorage::class)->delete($gallery->image_path); $gallery->delete(); return back()->with('success', 'Gallery item deleted.'); }
    private function validated(Request $request, bool $imageRequired = true): array
    {
        $data = $request->validate([
            'is_featured' => ['nullable', 'boolean'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        unset($data['image']);
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }
}
