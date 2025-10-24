<div class="card hover:shadow-xl transition duration-300">
    <a href="{{ route('mods.show', [$mod->primary_category, $mod]) }}" class="block">
        <div class="relative">
            <img src="{{ $mod->hero_image_url }}" alt="{{ $mod->title }}" class="w-full h-44 object-cover rounded-t-xl">
            <div class="absolute bottom-0 left-0 right-0 p-2 bg-gradient-to-t from-black/70 to-transparent text-white text-xs">
                <div class="flex justify-between items-center">
                    <span class="flex items-center font-semibold text-yellow-400"><i class="fa-solid fa-star mr-1"></i>{{ number_format($mod->rating, 1) }}</span>
                    <div class="flex items-center space-x-3">
                        <span class="flex items-center"><i class="fa-solid fa-thumbs-up mr-1"></i>{{ number_format($mod->likes) }}</span>
                        <span class="flex items-center"><i class="fa-solid fa-download mr-1"></i>{{ number_format($mod->downloads) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-3">
            <h3 class="font-semibold text-gray-900 text-sm truncate" title="{{ $mod->title }}">{{ $mod->title }}</h3>
            <div class="flex justify-between items-center text-xs text-gray-500 mt-1">
                <span class="flex items-center"><i class="fa-solid fa-user mr-1"></i> {{ $mod->author->name }}</span>
                <span class="flex items-center"><i class="fa-solid fa-clock mr-1"></i>{{ $mod->published_at->diffForHumans() }}</span>
            </div>
        </div>
    </a>
</div>
