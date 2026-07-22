<?php

/*
|--------------------------------------------------------------------------
| Backend Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix("blogcategory")->group(function() {
    Route::get("/", [
        "as" => "admin.blog_category.index",
        "uses"  => "BlogCategoryController@index",
        "middleware" => "can:admin.blog_category.index"
    ]);

    Route::post("/filters", [
        "as" => "admin.blog_category.filters",
        "uses" => "BlogCategoryController@filters",
        "middleware" => "can:admin.blog_category.filters"
    ]);

    Route::get("/create", [
        "as" => "admin.blog_category.create",
        "uses" => "BlogCategoryController@create",
        "middleware" => "can:admin.blog_category.create"
    ]);

    Route::post("/", [
        "as" => "admin.blog_category.store",
        "uses" => "BlogCategoryController@store",
        "middleware" => "can:admin.blog_category.create"
    ]);

    Route::get("/edit/{id}", [
        "as" => "admin.blog_category.edit",
        "uses" => "BlogCategoryController@edit",
        "middleware" => "can:admin.blog_category.edit"
    ]);

    Route::put("/{id}", [
        "as" => "admin.blog_category.update",
        "uses" => "BlogCategoryController@update",
        "middleware" => "can:admin.blog_category.edit"
    ]);

    Route::post("/update_status", [
        "as" => "admin.blog_category.update_status",
        "uses" => "BlogCategoryController@updateStatus",
        "middleware" => "can:admin.blog_category.edit"
    ]);

    Route::delete("/delete/{id}", [
        "as" => "admin.blog_category.delete",
        "uses" => "BlogCategoryController@delete",
        "middleware" => "can:admin.blog_category.delete"
    ]);

    Route::delete("/massDelete", [
        "as" => "admin.blog_category.mass_delete",
        "uses" => "BlogCategoryController@massDelete",
        "middleware" => "can:admin.blog_category.mass_delete"
    ]);
});

Route::prefix("blogpost")->group(function() {
    Route::get("/", [
        "as" => "admin.blog_post.index",
        "uses" => "BlogPostController@index",
        "middleware" => "can:admin.blog_post.index"
    ]);

    Route::post("/filters", [
        "as" => "admin.blog_post.filters",
        "uses" => "BlogPostController@filters",
        "middleware" => "can:admin.blog_post.filters"
    ]);

    Route::get("/create", [
        "as" => "admin.blog_post.create",
        "uses" => "BlogPostController@create",
        "middleware" => "can:admin.blog_post.create"
    ]);

    Route::post("/", [
        "as" => "admin.blog_post.store",
        "uses" => "BlogPostController@store",
        "middleware" => "can:admin.blog_post.create"
    ]);

    Route::get("/edit/{id}", [
        "as" => "admin.blog_post.edit",
        "uses" => "BlogPostController@edit",
        "middleware" => "can:admin.blog_post.edit"
    ]);

    Route::put("/{id}", [
        "as" => "admin.blog_post.update",
        "uses" => "BlogPostController@update",
        "middleware" => "can:admin.blog_post.edit"
    ]);

    Route::post("/update_status", [
        "as" => "admin.blog_post.update_status",
        "uses" => "BlogPostController@updateStatus",
        "middleware" => "can:admin.blog_post.edit"
    ]);

    Route::delete("/delete/{id}", [
        "as" => "admin.blog_post.delete",
        "uses" => "BlogPostController@delete",
        "middleware" => "can:admin.blog_post.delete"
    ]);

    Route::delete("/massDelete", [
        "as" => "admin.blog_post.mass_delete",
        "uses" => "BlogPostController@massDelete",
        "middleware" => "can:admin.blog_post.mass_delete"
    ]);
});


Route::prefix("blogpostcomment")->group(function() {
    Route::get("/", [
        "as" => "admin.blog_post_comment.index",
        "uses" => "BlogPostCommentController@index",
        "middleware" => "can:admin.blog_post_comment.index"
    ]);

    Route::post("/filters", [
        "as" => "admin.blog_post_comment.filters",
        "uses" => "BlogPostCommentController@filters",
        "middleware" => "can:admin.blog_post_comment.filters"
    ]);

    Route::post("/", [
        "as" => "admin.blog_post_comment.store",
        "uses" => "BlogPostCommentController@store",
        "middleware" => "can:admin.blog_post_comment.create"
    ]);

    Route::get("/edit/{id}", [
        "as" => "admin.blog_post_comment.edit",
        "uses" => "BlogPostCommentController@edit",
        "middleware" => "can:admin.blog_post_comment.edit"
    ]);

    Route::put("/{id}", [
        "as" => "admin.blog_post_comment.update",
        "uses" => "BlogPostCommentController@update",
        "middleware" => "can:admin.blog_post_comment.edit"
    ]);

    Route::post("/update_status", [
        "as" => "admin.blog_post_comment.update_status",
        "uses" => "BlogPostCommentController@updateStatus",
        "middleware" => "can:admin.blog_post_comment.edit"
    ]);

    Route::delete("/delete/{id}", [
        "as" => "admin.blog_post_comment.delete",
        "uses" => "BlogPostCommentController@delete",
        "middleware" => "can:admin.blog_post_comment.delete"
    ]);

    Route::delete("/massDelete", [
        "as" => "admin.blog_post_comment.mass_delete",
        "uses" => "BlogPostCommentController@massDelete",
        "middleware" => "can:admin.blog_post_comment.mass_delete"
    ]);
});
        