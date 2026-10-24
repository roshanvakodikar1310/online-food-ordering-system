
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("foodSearch");
    const categoryList = document.getElementById("categoryList");
    const foodGrid = document.getElementById("foodGrid");
    const noResults = document.getElementById("noFoodResults");

    console.log("Food Ordering JavaScript loaded!");

    if (!searchInput || !categoryList || !foodGrid || !noResults) {
        console.error("Filter elements missing:", {
            searchInput: !!searchInput,
            categoryList: !!categoryList,
            foodGrid: !!foodGrid,
            noResults: !!noResults
        });
        return;
    }

    let selectedCategory = "all";

    function filterFoods() {
        const searchText = searchInput.value.trim().toLowerCase();
        const cards = foodGrid.querySelectorAll(".modern-food-card");
        let visibleCount = 0;

        cards.forEach(function (card) {
            const name = (card.dataset.name || "").toLowerCase();
            const category = (card.dataset.category || "").toLowerCase();

            const matchesSearch = name.includes(searchText);
            const matchesCategory =
                selectedCategory === "all" ||
                category === selectedCategory;

            const showCard = matchesSearch && matchesCategory;

            card.hidden = !showCard;

            if (showCard) {
                visibleCount++;
            }
        });

        noResults.hidden = visibleCount > 0;
        console.log("Visible food items:", visibleCount);
    }

    searchInput.addEventListener("input", filterFoods);

    categoryList.addEventListener("click", function (event) {
        const button = event.target.closest(".category-chip");

        if (!button) return;

        selectedCategory = (
            button.dataset.category || "all"
        ).trim().toLowerCase();

        categoryList.querySelectorAll(".category-chip").forEach(function (chip) {
            chip.classList.toggle("selected", chip === button);
        });

        filterFoods();
    });

    filterFoods();
});
