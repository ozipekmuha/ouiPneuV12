// === Global Notification System ===
function showGlobalNotification(message, type = 'success') {
    let notificationBar = document.getElementById('global-notification-bar');
    if (!notificationBar) {
        notificationBar = document.createElement('div');
        notificationBar.id = 'global-notification-bar';
        // Style it before appending to avoid flash of unstyled content if possible
        notificationBar.style.position = 'fixed';
        notificationBar.style.top = '-100px'; // Start off-screen
        notificationBar.style.left = '50%';
        notificationBar.style.transform = 'translateX(-50%)';
        notificationBar.style.padding = '1rem 2rem';
        notificationBar.style.borderRadius = '0 0 8px 8px'; // var(--border-radius-medium)
        notificationBar.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
        notificationBar.style.zIndex = '2000';
        notificationBar.style.transition = 'top 0.5s ease-in-out';
        notificationBar.style.textAlign = 'center';
        notificationBar.style.minWidth = '280px';
        notificationBar.style.maxWidth = '80%';
        document.body.appendChild(notificationBar);
    }

    notificationBar.textContent = message;
    // Applying base class and type-specific class
    notificationBar.className = 'global-notification-bar'; // Reset classes
    notificationBar.classList.add(type);


    // Force reflow to ensure transition is applied if element was just created
    void notificationBar.offsetWidth;

    notificationBar.classList.add('show'); // This class should control 'top: 0;'

    // Cacher après quelques secondes
    setTimeout(() => {
        notificationBar.classList.remove('show');
    }, 4000);
}


document.addEventListener('DOMContentLoaded', () => {
    // Initialize AOS (Animate On Scroll)
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 700,
            once: true,
            offset: 120,
            easing: 'ease-in-out',
        });
    }

    // Hamburger Menu Logic
    const headerHamburgerButton = document.getElementById('hamburger-button');
    const mainNavLinks = document.getElementById('main-nav-links');

    function toggleMainMenu() {
        // console.log('toggleMainMenu called'); // For debugging if needed
        if (mainNavLinks && headerHamburgerButton) {
            const isActive = mainNavLinks.classList.toggle('is-active');
            mainNavLinks.setAttribute('aria-hidden', String(!isActive));
            headerHamburgerButton.classList.toggle('is-active', isActive);
            headerHamburgerButton.setAttribute('aria-expanded', String(isActive));

            if (isActive) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        } else {
            // This console.error should ideally not appear if HTML is correct
            console.error("Hamburger menu elements (mainNavLinks or headerHamburgerButton) not found during toggle.");
        }
    }

    // Ensure elements are found before adding listener
    if (mainNavLinks && headerHamburgerButton) {
        headerHamburgerButton.addEventListener('click', toggleMainMenu);
        mainNavLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (mainNavLinks.classList.contains('is-active')) {
                    toggleMainMenu();
                }
            });
        });
    }

    // Scroll-activated shadow for sticky header
    const header = document.getElementById('main-header');
    if (header) {
        window.addEventListener('scroll', () => {
            header.classList.toggle('header-scrolled', window.scrollY > 50);
        });
    }

    // Product Filtering Logic
    const productFiltersForm = document.getElementById('product-filters-form');

    // Make allFilterData global to this scope so it can be shared/checked by product page filters
    let allFilterOptionsData = null;

    async function ensureFilterDataFetched() {
        if (!allFilterOptionsData) {
            try {
                const response = await fetch('get_filter_options.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                allFilterOptionsData = await response.json();
                if (allFilterOptionsData.error) {
                    console.error("Error from get_filter_options.php:", allFilterOptionsData.error);
                    allFilterOptionsData = null; // Reset on error
                }
            } catch (error) {
                console.error("Could not fetch filter options for product page:", error);
                allFilterOptionsData = null;
            }
        }
        return allFilterOptionsData;
    }

    // Generic function to populate select, used by both quick filter and product page filters
    function populateSelectWithOptionsGeneric(selectElement, options, placeholderValue, placeholderText, valuePrefix = '', textPrefix = '') {
        if (!selectElement) return;
        const currentValue = selectElement.value; // Preserve current selection if possible
        selectElement.innerHTML = `<option value="${placeholderValue}">${placeholderText}</option>`;
        const fragment = document.createDocumentFragment();
        options.forEach(optValue => {
            const option = document.createElement('option');
            option.value = valuePrefix + optValue;
            option.textContent = textPrefix + optValue;
            fragment.appendChild(option);
        });
        selectElement.appendChild(fragment);
        // Try to restore previous selection
        if (Array.from(selectElement.options).some(opt => opt.value === currentValue)) {
            selectElement.value = currentValue;
        }
    }


    if (productFiltersForm) {
        let allProductsDomData = []; // Data parsed from DOM elements for client-side filtering
        const productGrid = document.querySelector('#all-products .product-grid');

        const filterWidthSelect = document.getElementById('filter-width');
        const filterRatioSelect = document.getElementById('filter-ratio');
        const filterDiameterSelect = document.getElementById('filter-diameter');
        const filterBrandSelect = document.getElementById('filter-brand');
        const filterTypeSelect = document.getElementById('filter-type'); // Saison
        const filterRunflatCheckbox = document.getElementById('filter-runflat');
        const filterReinforcedCheckbox = document.getElementById('filter-reinforced');
        const resetFiltersButton = document.getElementById('reset-filters-button');
        const applyFiltersAndCloseButton = document.getElementById('apply-filters-button');


        function parseProductCard(cardElement) {
            // dataset attributes are preferred for reliable data retrieval
            const name = cardElement.dataset.name || 'N/A';
            const brand = cardElement.dataset.brand || '';
            const width = cardElement.dataset.width || '';
            const ratio = cardElement.dataset.ratio || '';
            const diameter = cardElement.dataset.diameter || '';
            const type = cardElement.dataset.type || ''; // Saison from data-type
            const price = parseFloat(cardElement.dataset.price) || 0;
            const runflat = cardElement.dataset.runflat === 'true';
            const reinforced = cardElement.dataset.reinforced === 'true';

            return { name, brand, width, ratio, diameter, type, price, runflat, reinforced, domElement: cardElement };
        }

        function extractProductDataFromDOM() {
            if (!productGrid) return [];
            const cards = productGrid.querySelectorAll('.product-card');
            return Array.from(cards).map(card => parseProductCard(card));
        }

        async function initializeProductPageFilters() {
            allProductsDomData = extractProductDataFromDOM();
            const fetchedOptions = await ensureFilterDataFetched();

            if (fetchedOptions && !fetchedOptions.error) {
                populateSelectWithOptionsGeneric(filterWidthSelect, fetchedOptions.largeurs, "", "Tout");
                populateSelectWithOptionsGeneric(filterRatioSelect, [], "", "Tout"); // Init empty, depends on width
                populateSelectWithOptionsGeneric(filterDiameterSelect, [], "", "Tout", "", "R"); // Init empty, depends on width/ratio
                populateSelectWithOptionsGeneric(filterBrandSelect, fetchedOptions.marques, "", "Toutes");
                // Type (Saison) is usually static, so no need to populate from fetchedOptions unless it's dynamic
                // filterTypeSelect is already populated with static options in HTML

                filterRatioSelect.disabled = true;
                filterDiameterSelect.disabled = true;

                // Pre-fill filters from URL parameters
                prefillFiltersFromURL(fetchedOptions);
                applyFilters(); // Apply filters based on URL params or defaults

            } else {
                // Fallback: populate with data extracted from DOM if fetch fails
                const widthsFromDOM = [...new Set(allProductsDomData.map(p => p.width).filter(Boolean))].sort((a,b) => Number(a) - Number(b));
                const ratiosFromDOM = [...new Set(allProductsDomData.map(p => p.ratio).filter(Boolean))].sort((a,b) => Number(a) - Number(b));
                const diametersFromDOM = [...new Set(allProductsDomData.map(p => p.diameter).filter(Boolean))].sort((a,b) => Number(a) - Number(b));
                const brandsFromDOM = [...new Set(allProductsDomData.map(p => p.brand).filter(Boolean))].sort();

                populateSelectWithOptionsGeneric(filterWidthSelect, widthsFromDOM, "", "Tout");
                populateSelectWithOptionsGeneric(filterRatioSelect, ratiosFromDOM, "", "Tout");
                populateSelectWithOptionsGeneric(filterDiameterSelect, diametersFromDOM, "", "Tout", "", "R");
                populateSelectWithOptionsGeneric(filterBrandSelect, brandsFromDOM, "", "Toutes");

                // Pre-fill filters from URL parameters even with DOM data
                prefillFiltersFromURL(null); // Pass null as fetchedOptions unavailable
                applyFilters();
            }
        }

        function updateProductPageDependentFilters() {
            if (!allFilterOptionsData || allFilterOptionsData.error) { // If fetched data is not available, disable dependent logic for now
                filterRatioSelect.disabled = !filterWidthSelect.value;
                filterDiameterSelect.disabled = !filterWidthSelect.value || !filterRatioSelect.value;
                return;
            }

            const selectedWidth = filterWidthSelect.value;
            const selectedRatio = filterRatioSelect.value;

            if (selectedWidth) {
                const availableHauteurs = [...new Set(
                    allFilterOptionsData.dimensions_completes
                        .filter(dim => dim.l === selectedWidth)
                        .map(dim => dim.h)
                )].sort((a, b) => Number(a) - Number(b));
                populateSelectWithOptionsGeneric(filterRatioSelect, availableHauteurs, "", "Tout");
                filterRatioSelect.disabled = availableHauteurs.length === 0;
            } else {
                populateSelectWithOptionsGeneric(filterRatioSelect, [], "", "Tout");
                filterRatioSelect.disabled = true;
                filterRatioSelect.value = "";
            }

            if (selectedWidth && selectedRatio) {
                const availableDiameters = [...new Set(
                    allFilterOptionsData.dimensions_completes
                        .filter(dim => dim.l === selectedWidth && dim.h === selectedRatio)
                        .map(dim => dim.d)
                )].sort((a, b) => Number(a) - Number(b));
                populateSelectWithOptionsGeneric(filterDiameterSelect, availableDiameters, "", "Tout", "", "R");
                filterDiameterSelect.disabled = availableDiameters.length === 0;
            } else {
                populateSelectWithOptionsGeneric(filterDiameterSelect, [], "", "Tout", "", "R");
                filterDiameterSelect.disabled = true;
                filterDiameterSelect.value = "";
            }
        }

        if (filterWidthSelect) {
            filterWidthSelect.addEventListener('change', () => {
                filterRatioSelect.value = "";
                filterDiameterSelect.value = "";
                updateProductPageDependentFilters();
                // No direct applyFilters() here, user clicks "Apply Filters" button
            });
        }
        if (filterRatioSelect) {
            filterRatioSelect.addEventListener('change', () => {
                filterDiameterSelect.value = "";
                updateProductPageDependentFilters();
                // No direct applyFilters() here
            });
        }


        function applyFilters() {
            if (!allProductsDomData || allProductsDomData.length === 0) return;

            const selectedWidth = filterWidthSelect ? filterWidthSelect.value : "";
            const selectedRatio = filterRatioSelect ? filterRatioSelect.value : "";
            const selectedDiameter = filterDiameterSelect ? filterDiameterSelect.value : "";
            const selectedBrand = filterBrandSelect ? filterBrandSelect.value : "";
            const selectedType = filterTypeSelect ? filterTypeSelect.value : ""; // Saison
            const isRunflatSelected = filterRunflatCheckbox ? filterRunflatCheckbox.checked : false;
            const isReinforcedSelected = filterReinforcedCheckbox ? filterReinforcedCheckbox.checked : false;

            let visibleCount = 0;
            allProductsDomData.forEach(product => {
                let matches = true;
                if (selectedWidth && product.width !== selectedWidth) matches = false;
                if (selectedRatio && product.ratio !== selectedRatio) matches = false;
                if (selectedDiameter && product.diameter !== selectedDiameter) matches = false; // product.diameter is just '16', selectedDiameter is 'R16'
                if (selectedBrand && product.brand !== selectedBrand) matches = false;
                if (selectedType && product.type !== selectedType) matches = false;

                if (isRunflatSelected && !product.runflat) matches = false;
                if (isReinforcedSelected && !product.reinforced) matches = false;

                product.domElement.classList.toggle('product-hidden', !matches);
                if (matches) visibleCount++;
            });

            const productCountElement = document.getElementById('product-results-count');
            if (productCountElement) {
                productCountElement.textContent = visibleCount;
            }


            if (typeof AOS !== 'undefined') {
                AOS.refresh();
            }
        }

        if (allProductsDomData.length === 0 && productGrid) { // Check if data needs to be extracted
           initializeProductPageFilters(); // This will also call ensureFilterDataFetched
        } else if (productGrid) { // Data might be populated by PHP, but filters still need init
           initializeProductPageFilters();
        }


        // Event listeners for filter changes (but apply is manual via button)
        // The actual filtering is done when "Appliquer les Filtres" is clicked.
        // So, we don't need individual change listeners on each input to call applyFilters().
        // updateProductPageDependentFilters handles the dynamic select options.

        if (resetFiltersButton) {
            resetFiltersButton.addEventListener('click', () => {
                filterWidthSelect.value = "";
                filterRatioSelect.value = "";
                filterDiameterSelect.value = "";
                filterBrandSelect.value = "";
                filterTypeSelect.value = "";
                if(filterRunflatCheckbox) filterRunflatCheckbox.checked = false;
                if(filterReinforcedCheckbox) filterReinforcedCheckbox.checked = false;

                updateProductPageDependentFilters(); // Reset dependent dropdowns state
                applyFilters(); // Apply after reset
                // Optionally close panel if open: closeFiltersPanel();
            });
        }

        if (applyFiltersAndCloseButton) { // This is the button inside the filter panel
            applyFiltersAndCloseButton.addEventListener('click', () => {
                applyFilters();
                closeFiltersPanel(); // This function is defined below
            });
        }


        // === Off-Canvas Filter Panel Logic (produits.php) ===
        const openFiltersPanelButton = document.getElementById('open-filters-panel');
        const closeFiltersPanelButton = document.getElementById('close-filters-panel');
        const filtersPanel = document.getElementById('filters-panel');
        const filtersOverlay = document.getElementById('filters-overlay');
        // const applyFiltersAndCloseButton = document.getElementById('apply-filters-button'); // Déclaration redondante, déjà défini plus haut dans le scope de productFiltersForm

        function openFiltersPanel() {
            if (filtersPanel && filtersOverlay) {
                filtersPanel.classList.add('is-active');
                filtersOverlay.classList.add('is-active');
                document.body.style.overflow = 'hidden';
                document.documentElement.style.overflow = 'hidden'; // Empêcher scroll sur <html>
                // document.body.style.overflowX = 'hidden'; // Optionnel si overflow:hidden ne suffit pas
                // document.documentElement.style.overflowX = 'hidden'; // Optionnel
            }
        }

        function closeFiltersPanel() {
            if (filtersPanel && filtersOverlay) {
                filtersPanel.classList.remove('is-active');
                filtersOverlay.classList.remove('is-active');
                document.body.style.overflow = '';
                document.documentElement.style.overflow = ''; // Réinitialiser scroll sur <html>
                // document.body.style.overflowX = '';
                // document.documentElement.style.overflowX = '';
            }
        }

        if (openFiltersPanelButton) {
            openFiltersPanelButton.addEventListener('click', openFiltersPanel);
        }
        if (closeFiltersPanelButton) {
            closeFiltersPanelButton.addEventListener('click', closeFiltersPanel);
        }
        if (filtersOverlay) {
            filtersOverlay.addEventListener('click', closeFiltersPanel);
        }
        if (applyFiltersAndCloseButton) {
            applyFiltersAndCloseButton.addEventListener('click', () => {
                applyFilters();
                closeFiltersPanel();
            });
        }

        // Pre-fill filters from URL parameters on produits.php
        if (window.location.pathname.endsWith('produits.php') && allProductsData.length > 0) {
            const urlParams = new URLSearchParams(window.location.search);
            let filtersAppliedFromUrl = false;

            if (urlParams.has('width') && filterWidthSelect) {
                filterWidthSelect.value = urlParams.get('width');
                filtersAppliedFromUrl = true;
            }
            if (urlParams.has('ratio') && filterRatioSelect) {
                filterRatioSelect.value = urlParams.get('ratio');
                filtersAppliedFromUrl = true;
            }
            if (urlParams.has('diameter') && filterDiameterSelect) {
                filterDiameterSelect.value = urlParams.get('diameter');
                filtersAppliedFromUrl = true;
            }
            if (urlParams.has('type') && filterTypeSelect) {
                filterTypeSelect.value = urlParams.get('type');
                filtersAppliedFromUrl = true;
            }

            if (filtersAppliedFromUrl) {
                applyFilters();
            }
        }

        // === Product Sorting Logic (produits.html) ===
        const sortBySelect = document.getElementById('sort-by');
        if (sortBySelect && productGrid) {
            sortBySelect.addEventListener('change', (event) => {
                const sortBy = event.target.value;
                sortProducts(sortBy);
            });
        }

        function sortProducts(criteria) {
            if (!productGrid || !allProductsData || allProductsData.length === 0) return;

            let visibleProductsData = allProductsData.filter(p => !p.domElement.classList.contains('product-hidden'));

            if (criteria === 'price-asc') {
                visibleProductsData.sort((a, b) => a.price - b.price);
            } else if (criteria === 'price-desc') {
                visibleProductsData.sort((a, b) => b.price - a.price);
            } else if (criteria === 'name-asc') {
                visibleProductsData.sort((a, b) => a.name.localeCompare(b.name));
            }
            else if (criteria === 'relevance') {
                const currentlyFilteredInOriginalOrder = allProductsData.filter(p => !p.domElement.classList.contains('product-hidden'));
                visibleProductsData = currentlyFilteredInOriginalOrder;
            }

            const allCardsInGrid = Array.from(productGrid.querySelectorAll('.product-card'));
            allCardsInGrid.forEach(card => card.remove());

            visibleProductsData.forEach(productData => {
                productGrid.appendChild(productData.domElement);
            });

            allProductsData.forEach(productData => {
                if (productData.domElement.classList.contains('product-hidden')) {
                    productGrid.appendChild(productData.domElement);
                }
            });

            if (typeof AOS !== 'undefined') {
                AOS.refresh();
            }
        }
    }

    // === Address Modal JS ===
    const addressModalOverlay = document.getElementById('address-modal');
    if (addressModalOverlay) {
        const openModalButton = document.getElementById('add-new-address-button'); // Updated ID
        const closeModalButton = addressModalOverlay.querySelector('.modal-close-button');
        const cancelModalButton = addressModalOverlay.querySelector('.modal-cancel-button');
        const addressForm = document.getElementById('address-form');
        const modalTitle = addressModalOverlay.querySelector('#modal-title');
        const addressIdInput = addressModalOverlay.querySelector('#address-id');

        function openModal(isEdit = false, data = null) {
            if (addressForm) addressForm.reset(); // Reset form first

            if (isEdit && data) {
                if (modalTitle) modalTitle.textContent = 'Modifier l\'adresse';
                if (addressIdInput) addressIdInput.value = data.id_adresse;

                // Populate form fields, carefully matching names
                // Names in modal form: type_adresse, modal_firstname, modal_lastname, adresse_ligne1, etc.
                // Names in data (from DB): type_adresse, destinataire_nom_complet, adresse_ligne1, etc.
                addressForm.elements['type_adresse'].value = data.type_adresse || '';

                const nameParts = data.destinataire_nom_complet ? data.destinataire_nom_complet.split(' ') : ['', ''];
                addressForm.elements['modal_firstname'].value = nameParts[0] || ''; // Assuming first part is firstname
                addressForm.elements['modal_lastname'].value = nameParts.slice(1).join(' ') || ''; // Rest is lastname

                addressForm.elements['adresse_ligne1'].value = data.adresse_ligne1 || '';
                addressForm.elements['adresse_ligne2'].value = data.adresse_ligne2 || '';
                addressForm.elements['code_postal'].value = data.code_postal || '';
                addressForm.elements['ville'].value = data.ville || '';
                addressForm.elements['pays'].value = data.pays || 'France';
                addressForm.elements['telephone_contact'].value = data.telephone_contact || '';
                addressForm.elements['est_principale_livraison'].checked = !!data.est_principale_livraison;
                addressForm.elements['est_principale_facturation'].checked = !!data.est_principale_facturation;

            } else {
                if (modalTitle) modalTitle.textContent = 'Ajouter une nouvelle adresse';
                if (addressIdInput) addressIdInput.value = ''; // Clear ID for new address
                // Default country or other fields can be set here if needed
                if(addressForm.elements['pays']) addressForm.elements['pays'].value = 'France';
            }

            addressModalOverlay.style.display = 'flex';
            setTimeout(() => {
                addressModalOverlay.classList.add('is-visible');
                document.body.style.overflow = 'hidden';
            }, 10);
        }

        function closeModal() {
            addressModalOverlay.classList.remove('is-visible');
            // Use a shorter timeout for display:none to avoid issues if transitionend doesn't fire
            setTimeout(() => {
                if (!addressModalOverlay.classList.contains('is-visible')) {
                    addressModalOverlay.style.display = 'none';
                }
                 document.body.style.overflow = '';
            }, 300); // Match transition duration (approx)
        }

        // Ensure modal is hidden on page load if not 'is-visible'
        // This timeout logic might be problematic, better to rely on initial CSS
        // setTimeout(() => {
        //     if (!addressModalOverlay.classList.contains('is-visible')) {
        //          addressModalOverlay.style.display = 'none';
        //          document.body.style.overflow = '';
        //     }
        // }, 350);


        if (openModalButton) {
            openModalButton.addEventListener('click', () => {
                openModal(false); // Open for new address
            });
        }

        // Event delegation for edit address links
        const addressListContainer = document.getElementById('address-list-container');
        if (addressListContainer) {
            addressListContainer.addEventListener('click', function(event) {
                const editLink = event.target.closest('a.edit-address-link');
                if (editLink) {
                    event.preventDefault();
                    try {
                        const addressDataString = editLink.dataset.address;
                        if (addressDataString) {
                            const addressData = JSON.parse(addressDataString);
                            openModal(true, addressData);
                        } else {
                            console.error('Address data not found on edit link.');
                        }
                    } catch (e) {
                        console.error('Error parsing address data for edit:', e);
                    }
                }
            });
        }


        if (closeModalButton) closeModalButton.addEventListener('click', closeModal);
        if (cancelModalButton) cancelModalButton.addEventListener('click', closeModal);

        addressModalOverlay.addEventListener('click', (event) => {
            if (event.target === addressModalOverlay) closeModal();
        });

        if (addressForm) {
            addressForm.addEventListener('submit', (event) => {
                event.preventDefault();
                showGlobalNotification('Adresse enregistrée (simulation) !', 'success');
                closeModal();
            });
        }
    }


    // === Quick Filter on Index Page ===
    const quickFilterForm = document.getElementById('quick-filter-form');
    if (quickFilterForm) {
        const qfWidthSelect = document.getElementById('qf-width');
        const qfRatioSelect = document.getElementById('qf-ratio');
        const qfDiameterSelect = document.getElementById('qf-diameter');
        const qfChargeSelect = document.getElementById('charge'); // ID from index.php
        const qfVitesseSelect = document.getElementById('vitesse'); // ID from index.php
        const qfMarqueSelect = document.getElementById('marque'); // ID from index.php
        // Note: Saison ('qf-type') and Spécificité ('specificite') are already populated or handled differently.

        let allFilterData = {
            largeurs: [],
            hauteurs: [],
            diametres: [],
            charges: [],
            vitesses: [],
            marques: [],
            dimensions_completes: []
        };

        function populateSelectWithOptionsGeneric(selectElement, options, placeholderValue, placeholderText, valuePrefix = '', textPrefix = '') {
            if (!selectElement) return;
            selectElement.innerHTML = `<option value="${placeholderValue}">${placeholderText}</option>`; // Clear existing options and add placeholder
            const fragment = document.createDocumentFragment();
            options.forEach(optValue => {
                const option = document.createElement('option');
                option.value = valuePrefix + optValue;
                option.textContent = textPrefix + optValue;
                fragment.appendChild(option);
            });
            selectElement.appendChild(fragment);
        }

        async function fetchAndPopulateQuickFilters() {
            try {
                const response = await fetch('get_filter_options.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                allFilterData = await response.json();

                if (allFilterData.error) {
                    console.error("Error from get_filter_options.php:", allFilterData.error);
                    // Populate with some defaults or show error
                    populateSelectWithOptionsGeneric(qfWidthSelect, ["195", "205", "225"], "", "Largeur");
                    populateSelectWithOptionsGeneric(qfRatioSelect, ["55", "60", "65"], "", "Hauteur");
                    populateSelectWithOptionsGeneric(qfDiameterSelect, ["15", "16", "17"], "", "Diamètre", "", "R");
                    return;
                }

                // Initial population
                populateSelectWithOptionsGeneric(qfWidthSelect, allFilterData.largeurs, "", "Largeur");
                populateSelectWithOptionsGeneric(qfRatioSelect, [], "", "Hauteur"); // Empty initially
                populateSelectWithOptionsGeneric(qfDiameterSelect, [], "", "Diamètre", "", "R"); // Empty initially
                populateSelectWithOptionsGeneric(qfChargeSelect, allFilterData.charges, "", "Charge");
                populateSelectWithOptionsGeneric(qfVitesseSelect, allFilterData.vitesses, "", "Vitesse");
                populateSelectWithOptionsGeneric(qfMarqueSelect, allFilterData.marques, "", "Marque");

                qfRatioSelect.disabled = true;
                qfDiameterSelect.disabled = true;

            } catch (error) {
                console.error("Could not fetch filter options:", error);
                // Fallback or error message to user
                populateSelectWithOptionsGeneric(qfWidthSelect, ["195", "205", "225"], "", "Largeur");
                populateSelectWithOptionsGeneric(qfRatioSelect, ["55", "60", "65"], "", "Hauteur");
                populateSelectWithOptionsGeneric(qfDiameterSelect, ["15", "16", "17"], "", "Diamètre", "", "R");
            }
        }

        function updateDependentFilters() {
            const selectedWidth = qfWidthSelect.value;
            const selectedRatio = qfRatioSelect.value;

            // Update Hauteurs based on Largeur
            if (selectedWidth) {
                const availableHauteurs = [...new Set(
                    allFilterData.dimensions_completes
                        .filter(dim => dim.l === selectedWidth)
                        .map(dim => dim.h)
                )].sort((a, b) => Number(a) - Number(b));
                populateSelectWithOptionsGeneric(qfRatioSelect, availableHauteurs, "", "Hauteur");
                qfRatioSelect.disabled = availableHauteurs.length === 0;
            } else {
                populateSelectWithOptionsGeneric(qfRatioSelect, [], "", "Hauteur");
                qfRatioSelect.disabled = true;
                qfRatioSelect.value = ""; // Reset
            }

            // Update Diamètres based on Largeur and Hauteur
            if (selectedWidth && selectedRatio) {
                const availableDiameters = [...new Set(
                    allFilterData.dimensions_completes
                        .filter(dim => dim.l === selectedWidth && dim.h === selectedRatio)
                        .map(dim => dim.d)
                )].sort((a, b) => Number(a) - Number(b));
                populateSelectWithOptionsGeneric(qfDiameterSelect, availableDiameters, "", "Diamètre", "", "R");
                qfDiameterSelect.disabled = availableDiameters.length === 0;
            } else {
                populateSelectWithOptionsGeneric(qfDiameterSelect, [], "", "Diamètre", "", "R");
                qfDiameterSelect.disabled = true;
                qfDiameterSelect.value = ""; // Reset
            }
             // TODO: Potentially update Charge/Vitesse based on L/H/D selected if needed
        }

        if (qfWidthSelect) {
            qfWidthSelect.addEventListener('change', () => {
                qfRatioSelect.value = ""; // Reset ratio
                qfDiameterSelect.value = ""; // Reset diameter
                updateDependentFilters();
            });
        }
        if (qfRatioSelect) {
            qfRatioSelect.addEventListener('change', () => {
                qfDiameterSelect.value = ""; // Reset diameter
                updateDependentFilters();
            });
        }

        fetchAndPopulateQuickFilters(); // Load initial data

        quickFilterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const width = qfWidthSelect.value;
            const ratio = qfRatioSelect.value;
            const diameter = qfDiameterSelect.value;
            const type = document.getElementById('qf-type').value; // Saison
            const charge = qfChargeSelect.value;
            const vitesse = qfVitesseSelect.value;
            const marque = qfMarqueSelect.value;
            const specificite = document.getElementById('specificite').value; // Spécificité
            const runflat = quickFilterForm.querySelector('input[name="runflat"]').checked;
            const renforce = quickFilterForm.querySelector('input[name="renforce"]').checked;


            const queryParams = new URLSearchParams();
            if (width) queryParams.set('width', width);
            if (ratio) queryParams.set('ratio', ratio);
            if (diameter) queryParams.set('diameter', diameter);
            if (type) queryParams.set('type', type);
            if (charge) queryParams.set('charge', charge);
            if (vitesse) queryParams.set('vitesse', vitesse);
            if (marque) queryParams.set('marque', marque);
            if (specificite) queryParams.set('specificite', specificite);
            if (runflat) queryParams.set('runflat', 'true');
            if (renforce) queryParams.set('renforce', 'true');

            window.location.href = `produits.php?${queryParams.toString()}`;
        });
    }


    // === Accordion Functionality (produit.html) ===
    const accordionItems = document.querySelectorAll('.accordion-item');
    if (accordionItems.length > 0) {
        accordionItems.forEach(item => {
            const header = item.querySelector('.accordion-header');
            const content = item.querySelector('.accordion-content');

            if (header && content) {
                header.addEventListener('click', () => {
                    const isExpanded = header.getAttribute('aria-expanded') === 'true';

                    accordionItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            const otherHeader = otherItem.querySelector('.accordion-header');
                            const otherContent = otherItem.querySelector('.accordion-content');
                            if (otherHeader && otherContent) {
                                otherHeader.setAttribute('aria-expanded', 'false');
                                otherContent.style.maxHeight = null;
                            }
                        }
                    });

                    header.setAttribute('aria-expanded', String(!isExpanded));
                    content.style.maxHeight = !isExpanded ? content.scrollHeight + 'px' : null;
                });
            }
        });

        const firstAccordionHeader = accordionItems[0]?.querySelector('.accordion-header');
        const firstAccordionContent = accordionItems[0]?.querySelector('.accordion-content');
        if (firstAccordionHeader && firstAccordionContent && firstAccordionHeader.getAttribute('aria-expanded') === 'true') {
            firstAccordionContent.style.maxHeight = firstAccordionContent.scrollHeight + 'px';
        }
    }

    // === Product Image Gallery (produit.html) ===
    const mainProductImage = document.getElementById('main-product-image');
    const thumbnailImages = document.querySelectorAll('.thumbnail-image');

    if (mainProductImage && thumbnailImages.length > 0) {
        thumbnailImages.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainProductImage.src = this.dataset.fullimage || this.src;
                mainProductImage.alt = this.alt;

                thumbnailImages.forEach(t => t.classList.remove('active-thumbnail'));
                this.classList.add('active-thumbnail');
            });
        });
    }

    // === Quantity Selector (produit.php) ===
    const productDetailSection = document.getElementById('product-detail-section');
    if (productDetailSection) { // Only run this logic if we are on a product detail page
        const quantitySelectorInProductPage = productDetailSection.querySelector('.quantity-selector');
        if (quantitySelectorInProductPage) {
            const quantityInputProductPage = quantitySelectorInProductPage.querySelector('input[type="number"]');
            const minusBtnProduct = quantitySelectorInProductPage.querySelector('.quantity-btn.minus');
            const plusBtnProduct = quantitySelectorInProductPage.querySelector('.quantity-btn.plus');

            if (quantityInputProductPage && minusBtnProduct && plusBtnProduct) {
                function updateQuantityButtonsProductPage() {
                    const currentValue = parseInt(quantityInputProductPage.value);
                    const min = parseInt(quantityInputProductPage.min);
                    const max = parseInt(quantityInputProductPage.max);
                    minusBtnProduct.disabled = currentValue <= min;
                    plusBtnProduct.disabled = currentValue >= max;
                }

                minusBtnProduct.addEventListener('click', () => {
                    let currentValue = parseInt(quantityInputProductPage.value);
                    if (currentValue > parseInt(quantityInputProductPage.min)) {
                        quantityInputProductPage.value = currentValue - 1;
                        updateQuantityButtonsProductPage();
                    }
                });

                plusBtnProduct.addEventListener('click', () => {
                    let currentValue = parseInt(quantityInputProductPage.value);
                    if (currentValue < parseInt(quantityInputProductPage.max)) {
                        quantityInputProductPage.value = currentValue + 1;
                        updateQuantityButtonsProductPage();
                    }
                });

                quantityInputProductPage.addEventListener('change', updateQuantityButtonsProductPage);
                quantityInputProductPage.addEventListener('input', updateQuantityButtonsProductPage);
                updateQuantityButtonsProductPage(); // Initial state setup
            }
        }
    }

    // === Cart Item Count Update (Généralisé) ===
    const cartItemCountElements = document.querySelectorAll('.cart-item-count');
    let currentCartTotalItems = 0;

    function updateGlobalCartCount(count) {
        currentCartTotalItems = count;
        cartItemCountElements.forEach(el => {
            el.textContent = currentCartTotalItems;
            el.style.display = currentCartTotalItems > 0 ? 'inline-flex' : 'none';
        });
    }

    if (document.getElementById('cart-section')) {
        const initialCartItemsOnCartPage = document.querySelectorAll('#cart-section .cart-item');
        let totalUnits = 0;
        initialCartItemsOnCartPage.forEach(item => {
            const quantityInput = item.querySelector('.cart-item-quantity input[type="number"]');
            totalUnits += quantityInput ? parseInt(quantityInput.value) : 0;
        });
        updateGlobalCartCount(totalUnits);
    } else {
        const tempCartIconSpan = document.querySelector('.cart-icon span.cart-item-count');
        if (tempCartIconSpan && !isNaN(parseInt(tempCartIconSpan.textContent))) {
            updateGlobalCartCount(parseInt(tempCartIconSpan.textContent));
        } else {
            updateGlobalCartCount(0);
        }
    }

    // const allAddToCartButtons = document.querySelectorAll('.add-to-cart-button');
    // allAddToCartButtons.forEach(button => {
    //     // IMPORTANT: This event listener is commented out because it might interfere with the
    //     // default form submission behavior of the "Add to Cart" button, which is type="submit".
    //     // The PHP backend handles the actual cart addition.
    //     // This client-side logic was likely for immediate UI feedback but could prevent submission.
    //     button.addEventListener('click', () => {
    //         let quantity = 1;
    //         if (button.closest('#product-detail-section')) {
    //             const quantityInput = document.getElementById('quantity');
    //             quantity = quantityInput ? parseInt(quantityInput.value) : 1;
    //         }
    //         // updateGlobalCartCount(currentCartTotalItems + quantity); // This should be updated by server response or on page load
    //         // showGlobalNotification(`${quantity} article(s) ajouté(s) au panier !`, 'success'); // This should be triggered by server status
    //     });
    // });

    // === Cart Page Functionality ===
    const cartPage = document.getElementById('cart-section');
    if (cartPage) {
        const cartItemsColumn = cartPage.querySelector('.cart-items-column');
        
        function calculateCartTotals() {
            if (!cartItemsColumn) return;

            const cartItems = cartItemsColumn.querySelectorAll('.cart-item');
            let subtotal = 0;
            let totalItemUnits = 0;

            cartItems.forEach(item => {
                const priceUnitText = item.querySelector('.cart-item-price-unit')?.textContent || '€0';
                const priceUnit = parseFloat(priceUnitText.replace(/[^0-9,.]/g, '').replace(',', '.')) || 0;
                
                const quantityInput = item.querySelector('.cart-item-quantity input[type="number"]');
                const quantity = quantityInput ? parseInt(quantityInput.value) : 0;
                
                const itemTotalPriceElement = item.querySelector('.cart-item-total-price p');
                const itemTotal = priceUnit * quantity;

                if (itemTotalPriceElement) {
                    itemTotalPriceElement.textContent = `€${itemTotal.toFixed(2).replace('.', ',')}`;
                }
                subtotal += itemTotal;
                totalItemUnits += quantity;
            });

            const subtotalElement = document.getElementById('cart-subtotal');
            const totalElement = document.getElementById('cart-total');
            const asideSubtotalElement = document.getElementById('aside-cart-subtotal');
            const asideTotalElement = document.getElementById('aside-cart-total');
            
            const formattedSubtotal = `€${subtotal.toFixed(2).replace('.', ',')}`;
            if (subtotalElement) subtotalElement.textContent = formattedSubtotal;
            if (totalElement) totalElement.textContent = formattedSubtotal;
            if (asideSubtotalElement) asideSubtotalElement.textContent = formattedSubtotal;
            if (asideTotalElement) asideTotalElement.textContent = formattedSubtotal;
            
            updateGlobalCartCount(totalItemUnits); 
        }

        if (cartItemsColumn) {
            cartItemsColumn.addEventListener('change', (event) => {
                if (event.target.matches('.cart-item-quantity input[type="number"]')) {
                    // Allow setting quantity to 0 (for removal by PHP), but not negative.
                    // The input field itself has min="0".
                    if (parseInt(event.target.value) < 0) {
                        event.target.value = '0';
                    }
                    calculateCartTotals();
                }
            });

            cartItemsColumn.addEventListener('click', (event) => {
                if (event.target.closest('.remove-item-button')) {
                    const itemToRemove = event.target.closest('.cart-item');
                    if (itemToRemove) {
                        itemToRemove.remove();
                        calculateCartTotals();
                        showGlobalNotification('Article supprimé du panier.', 'info');
                    }
                }
            });
        }
        
        const clearCartButton = cartPage.querySelector('.clear-cart-button');
        if (clearCartButton && cartItemsColumn) {
            clearCartButton.addEventListener('click', () => {
                const allCartItems = cartItemsColumn.querySelectorAll('.cart-item');
                if (allCartItems.length > 0) {
                    allCartItems.forEach(item => item.remove());
                    calculateCartTotals();
                    showGlobalNotification('Panier vidé.', 'info');
                }
            });
        }
        calculateCartTotals();
    }

    // Mobile Search Toggle Logic
    const mobileSearchToggleButton = document.getElementById('mobile-search-toggle-button');
    const mainHeaderSearchBar = document.querySelector('#main-header .search-bar');

    if (mobileSearchToggleButton && mainHeaderSearchBar) {
        mobileSearchToggleButton.addEventListener('click', () => {
            const isActive = mainHeaderSearchBar.classList.toggle('mobile-active');
            mobileSearchToggleButton.setAttribute('aria-expanded', isActive);
            if (isActive) {
                mainHeaderSearchBar.querySelector('input[type="search"]').focus();
            }
        });
    }

    // Année dynamique dans le footer
    const currentYearSpan = document.getElementById('current-year');
    if (currentYearSpan) {
        currentYearSpan.textContent = new Date().getFullYear();
    }

    // Soumission simulée des formulaires avec notifications
    const loginForm = document.getElementById('login-form');
    // if (loginForm) {
    //     loginForm.addEventListener('submit', function(e) {
    //         e.preventDefault();
    //         showGlobalNotification('Connexion réussie ! Redirection...', 'success');
    //         setTimeout(() => window.location.href = 'dashboard.html', 1500);
    //     });
    // }

    const registerForm = document.getElementById('register-form');
    // if (registerForm) {
    //     registerForm.addEventListener('submit', function(e) {
    //         e.preventDefault();
    //         const password = document.getElementById('register-password').value;
    //         const confirmPassword = document.getElementById('register-confirm-password').value;
    //         const agreeTerms = document.getElementById('register-agree-terms').checked;

    //         if (password !== confirmPassword) {
    //             showGlobalNotification('Les mots de passe ne correspondent pas.', 'error');
    //             return;
    //         }
    //         if (!agreeTerms) {
    //             showGlobalNotification('Veuillez accepter les conditions générales et la politique de confidentialité.', 'error');
    //             return;
    //         }
    //         showGlobalNotification('Inscription réussie ! Vous pouvez maintenant vous connecter.', 'success');
    //         setTimeout(() => window.location.href = 'login.html', 2000);
    //     });
    // }

    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            showGlobalNotification('Message envoyé ! Nous vous répondrons bientôt.', 'success');
            contactForm.reset();
        });
    }

    // === Dashboard Tab Switching ===
    // NOTE: This was duplicated. Ensure only one DOMContentLoaded listener or merge them.
    // For now, assuming the first one is the main one and this might be a leftover.
    // If this is intended, ensure no variable redeclarations (e.g. dashboardNav).
    const dashboardNav = document.querySelector('.dashboard-nav');
    if (dashboardNav) {
        const navItems = dashboardNav.querySelectorAll('.dashboard-nav-item[data-target]');
        const contentSections = document.querySelectorAll('.dashboard-content-section');

        function switchTab(targetId) {
            contentSections.forEach(section => {
                section.classList.remove('is-active');
            });
            navItems.forEach(item => {
                item.classList.remove('is-active');
            });
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.classList.add('is-active');
            }
            const activeNavItem = dashboardNav.querySelector(`.dashboard-nav-item[data-target="${targetId}"]`);
            if (activeNavItem) {
                activeNavItem.classList.add('is-active');
            }
        }

        navItems.forEach(item => {
            item.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = item.getAttribute('data-target');
                if (targetId) {
                    switchTab(targetId);
                }
            });
        });
    }

    // === Order Detail Modal (dashboard.php) ===
    const orderDetailModalOverlay = document.getElementById('order-detail-modal');
    if (orderDetailModalOverlay) {
        const orderModalTitle = orderDetailModalOverlay.querySelector('#order-modal-title');
        const orderModalBodyContent = orderDetailModalOverlay.querySelector('#order-modal-body-content');
        const orderModalCloseButtons = orderDetailModalOverlay.querySelectorAll('.modal-close-button, .modal-cancel-button');

        function openOrderDetailModal(orderData) {
            if (!orderModalBodyContent || !orderModalTitle) return;

            orderModalTitle.textContent = `Détails de la Commande #${orderData.id_commande}`;

            let contentHtml = `
                <p><strong>Date:</strong> ${new Date(orderData.date_commande).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                <p><strong>Statut:</strong> ${orderData.statut_commande || 'N/A'}</p>
                <p><strong>Total Commande:</strong> ${parseFloat(orderData.montant_total_ttc).toFixed(2).replace('.', ',')} €</p>
                <hr style="margin: 1rem 0;">

                <h4>Adresse de Livraison:</h4>
                <p>${orderData.livraison_adresse_complete || 'Non spécifiée'}</p>

                <h4>Adresse de Facturation:</h4>
                <p>${orderData.facturation_adresse_complete || 'Non spécifiée'}</p>
                <hr style="margin: 1rem 0;">

                <h4>Articles Commandés:</h4>
            `;

            if (orderData.line_items && orderData.line_items.length > 0) {
                contentHtml += `<table class="order-summary-table" style="font-size:0.85rem;">
                                    <thead>
                                        <tr>
                                            <th style="width:60px;">Image</th>
                                            <th>Produit</th>
                                            <th style="text-align:right;">Qté</th>
                                            <th style="text-align:right;">Prix Unitaire TTC</th>
                                            <th style="text-align:right;">Total Ligne TTC</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                orderData.line_items.forEach(item => {
                    const prixUnitaireTTC = parseFloat(item.prix_unitaire_ttc_calc).toFixed(2).replace('.', ',');
                    const totalLigneTTC = parseFloat(item.total_ligne_ttc_calc).toFixed(2).replace('.', ',');
                    const imageUrl = item.image || 'https://placehold.co/50x50/1e1e1e/ffdd03?text=Pneu';
                    contentHtml += `<tr>
                                        <td><img src="${imageUrl}" alt="${item.nom_produit_commande || 'Produit'}" style="width:50px; height:auto;"></td>
                                        <td>${item.nom_produit_commande || 'N/A'}<br><small>${item.taille_produit_commande || ''}</small></td>
                                        <td style="text-align:right;">${item.quantite}</td>
                                        <td style="text-align:right;">${prixUnitaireTTC} €</td>
                                        <td style="text-align:right;">${totalLigneTTC} €</td>
                                    </tr>`;
                });
                contentHtml += `    </tbody>
                                </table>`;
            } else {
                contentHtml += "<p>Aucun article trouvé pour cette commande.</p>";
            }

            // Order Totals Summary (Subtotal, Shipping, etc. from Commandes table)
            const subTotalTTC = parseFloat(orderData.montant_sous_total / (1 - (parseFloat(orderData.taux_tva_applique) / 100)) ).toFixed(2).replace('.',','); // Approximation if not stored directly
                                // This calculation is likely wrong if montant_sous_total is HT.
                                // Assuming it's HT from schema, then TTC = HT * (1 + TVA_RATE)
                                // For now, let's use the values from Commandes table if they are reliable (e.g. montant_sous_total is HT)
                                // The schema for Commandes actually stores montant_sous_total (HT), montant_livraison (HT), montant_reduction (HT),
                                // montant_total_ht, montant_tva, montant_total_ttc.
                                // So we should use these directly.

            let subtotalForDisplay = (parseFloat(orderData.montant_total_ttc) - parseFloat(orderData.montant_livraison) + parseFloat(orderData.montant_reduction)).toFixed(2).replace('.',',');
            let itemsSubtotalTTC = 0;
            if (orderData.line_items && orderData.line_items.length > 0) {
                 orderData.line_items.forEach(item => itemsSubtotalTTC += parseFloat(item.total_ligne_ttc_calc));
            }

            const FIXED_JS_TVA_RATE = 0.20; // This should align with PHP's $TVA_RATE

            const shippingCostHT = parseFloat(orderData.montant_livraison || 0);
            const shippingCostTTC = shippingCostHT * (1 + FIXED_JS_TVA_RATE);

            const reductionAmountHT = parseFloat(orderData.montant_reduction || 0);
            const reductionAmountTTC = reductionAmountHT * (1 + FIXED_JS_TVA_RATE);

            contentHtml += `<div class="order-totals" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--border-color);">
                                <p><span>Sous-total des articles (TTC) :</span> <span>${itemsSubtotalTTC.toFixed(2).replace('.', ',')} €</span></p>
                                <p><span>Livraison (TTC) :</span> <span>${shippingCostTTC.toFixed(2).replace('.', ',')} €</span></p>
                                ${reductionAmountTTC > 0 ? `<p><span>Réduction (TTC) :</span> <span style="color:green;">-${reductionAmountTTC.toFixed(2).replace('.', ',')} €</span></p>` : ''}
                                <hr>
                                <p class="grand-total"><span>TOTAL PAYÉ TTC :</span> <span>${parseFloat(orderData.montant_total_ttc).toFixed(2).replace('.', ',')} €</span></p>
                            </div>`;


            orderModalBodyContent.innerHTML = contentHtml;

            orderDetailModalOverlay.style.display = 'flex';
            setTimeout(() => {
                orderDetailModalOverlay.classList.add('is-visible');
                document.body.style.overflow = 'hidden';
            }, 10);
        }

        function closeOrderDetailModal() {
            orderDetailModalOverlay.classList.remove('is-visible');
            setTimeout(() => {
                if (!orderDetailModalOverlay.classList.contains('is-visible')) {
                    orderDetailModalOverlay.style.display = 'none';
                }
                document.body.style.overflow = '';
            }, 300);
        }

        const dashboardContent = document.querySelector('.dashboard-content');
        if (dashboardContent) {
            dashboardContent.addEventListener('click', function(event) {
                const viewOrderLink = event.target.closest('a.view-order-details-link');
                if (viewOrderLink) {
                    event.preventDefault();
                    try {
                        const orderDataString = viewOrderLink.dataset.orderDetails;
                        if (orderDataString) {
                            const orderData = JSON.parse(orderDataString);
                            openOrderDetailModal(orderData);
                        } else {
                            console.error('Order data not found on view link.');
                            orderModalBodyContent.innerHTML = "<p>Erreur: Données de la commande non trouvées.</p>";
                            openOrderDetailModal({id_commande: 'Erreur'}); // Open modal with error
                        }
                    } catch (e) {
                        console.error('Error parsing order data:', e);
                        orderModalBodyContent.innerHTML = "<p>Erreur: Impossible de lire les données de la commande.</p>";
                        openOrderDetailModal({id_commande: 'Erreur'}); // Open modal with error
                    }
                }
            });
        }

        orderModalCloseButtons.forEach(button => {
            button.addEventListener('click', closeOrderDetailModal);
        });
        orderDetailModalOverlay.addEventListener('click', (event) => {
            if (event.target === orderDetailModalOverlay) {
                closeOrderDetailModal();
            }
        });
    }
});
