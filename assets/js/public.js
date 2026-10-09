document.addEventListener("DOMContentLoaded", function () {
    var slides = document.querySelectorAll(".hero-slide");
    var activeIdx = 0;

    if (slides.length > 1) {
        setInterval(function () {
            slides[activeIdx].classList.remove("is-active");
            activeIdx = (activeIdx + 1) % slides.length;
            slides[activeIdx].classList.add("is-active");
        }, 5500);
    }

    var searchInput = document.getElementById("liveSearchInput");
    var productGrid = document.getElementById("productsGrid");
    var productCards = document.querySelectorAll(".product-card-col");

    if (searchInput && productGrid) {
        searchInput.addEventListener("input", function (e) {
            var query = e.target.value.toLowerCase().trim();
            var matchCount = 0;

            productCards.forEach(function (card) {
                var name = card.getAttribute("data-name") || "";
                var category = card.getAttribute("data-category") || "";
                var visible = name.indexOf(query) !== -1 || category.indexOf(query) !== -1;
                card.style.display = visible ? "" : "none";
                if (visible) {
                    matchCount += 1;
                }
            });

            var emptyAlert = document.getElementById("searchEmptyAlert");
            if (matchCount === 0) {
                if (!emptyAlert) {
                    emptyAlert = document.createElement("div");
                    emptyAlert.id = "searchEmptyAlert";
                    emptyAlert.className = "col-12";
                    emptyAlert.innerHTML = '<div class="empty-state">Aucun médicament ne correspond à votre recherche.</div>';
                    productGrid.appendChild(emptyAlert);
                }
            } else if (emptyAlert) {
                emptyAlert.remove();
            }
        });
    }

    var revealEls = document.querySelectorAll(".reveal");
    if ("IntersectionObserver" in window) {
        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.classList.add("is-visible");
                obs.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

        revealEls.forEach(function (el) {
            observer.observe(el);
        });
    } else {
        revealEls.forEach(function (el) {
            el.classList.add("is-visible");
        });
    }
});
