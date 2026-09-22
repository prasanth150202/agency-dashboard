<x-app-layout title="Courses">
    <div>
        <h2 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Courses</h2>
        <p class="mt-1 text-sm text-ink-500">Grow your agency with BRIX training.</p>
    </div>

    <div class="mt-6 flex gap-2 border-b border-ink-200/70">
        <button type="button" class="border-b-2 border-brix-600 px-1 pb-3 text-sm font-medium text-brix-700">All Courses</button>
        <button type="button" class="border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-ink-400">In Progress</button>
        <button type="button" class="border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-ink-400">Completed</button>
    </div>

    <div class="mt-8 rounded-2xl border border-dashed border-ink-200 py-16 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-ink-100">
            <x-lucide-graduation-cap class="h-6 w-6 text-ink-400" />
        </div>
        <p class="mt-4 text-sm font-medium text-ink-700">No courses available yet</p>
        <p class="mt-1 text-sm text-ink-500">Check back soon — BRIX training will appear here.</p>
    </div>
</x-app-layout>
