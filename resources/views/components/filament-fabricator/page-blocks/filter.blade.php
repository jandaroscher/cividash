@aware(['page'])

<section class="py-8 bg-gray-50">
    <div class="container">
        <div class="flex flex-col xl:flex-row">
            @if(isset($label) && $label)
                <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto order-2 xl:order-1">{{ $label }}</h2>
            @endif
        </div>
        <div class="flex flex-wrap mb-15">
            @if(isset($show_categories) && $show_categories)
                {{-- Category filter button - functionality to be implemented --}}
                <button 
                    type="button"
                    class="flex basis-full md:basis-1/3 justify-center items-center h-16 text-gray-700 text-xl lg:text-2xl border border-gray-700 hover:bg-accent hover:text-white transition-colors hover:border-accent"
                    aria-label="Filter by categories"
                >
                    Kategorien
                </button>
            @endif
            
            @if(isset($show_search) && $show_search)
                <div class="flex-1 max-w-md">
                    <input 
                        type="text" 
                        placeholder="Suchen..." 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-accent focus:border-transparent"
                        aria-label="Search"
                    >
                </div>
            @endif
        </div>
    </div>
</section>



