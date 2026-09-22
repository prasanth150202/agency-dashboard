<?php

namespace App\Http\Controllers;

/**
 * Courses: a functional shell only. No course content exists yet — this
 * app has no course table, and none is invented here. Always empty until a
 * real course catalog is built.
 */
class CourseController extends Controller
{
    public function index()
    {
        return view('courses.index');
    }
}
