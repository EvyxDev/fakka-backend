<?php

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

function uploadImage(Request $request, $fieldName, $directory = 'images')
{
    if ($request->hasFile($fieldName)) {
        $image = $request->file($fieldName);
        $imageName = time() . '_' . $image->getClientOriginalName();
        $image->move(public_path($directory), $imageName);
        return $directory . '/' . $imageName;
    }
    return null;
}

function uploadImages(Request $request, $fieldName, $directory = 'images')
{
    $uploadedImages = [];

    if ($request->hasFile($fieldName)) {
        $images = $request->file($fieldName);

        foreach ($images as $image) {
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path($directory), $imageName);
            $uploadedImages[] = $directory . '/' . $imageName;
        }
    }

    return implode(',', $uploadedImages);
}
function updateImages(Request $request, $fieldName, $directory = 'images', $oldImages = [])
{
    $uploadedImages = [];

    if ($request->hasFile($fieldName)) {
        $images = $request->file($fieldName);

        foreach ($images as $image) {
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path($directory), $imageName);
            $uploadedImages[] = $directory . '/' . $imageName;
        }
    }

    foreach ($oldImages as $oldImage) {
        if (in_array($oldImage, $request->input('existing_images', []))) {
            $uploadedImages[] = $oldImage;
        } else {
            // Delete old image if not found in the new array
            if (file_exists(public_path($oldImage))) {
                unlink(public_path($oldImage));
            }
        }
    }

    return implode(',', $uploadedImages);
}
function deleteImage($imagePath)
{
    if ($imagePath && file_exists(public_path($imagePath))) {
        unlink(public_path($imagePath));
    }
}
function deleteImages($imagePaths)
{
    $paths = explode(',', $imagePaths);
    foreach ($paths as $imagePath) {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }
}

function paginate($query, $resourceClass, $limit = 10, $pageNumber = 1)
{
    // $paginatedData = $query->paginate($limit);
    $paginatedData = $query->paginate($limit, ['*'], 'page', $pageNumber);
    return $resourceClass::collection($paginatedData)->response()->getData(true);
}

