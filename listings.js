const token = sessionStorage.getItem('cc-auth-token');
const user = JSON.parse(sessionStorage.getItem('cc-user') || '{}');

// Redirect if not logged in
if (!token) {
    window.location.href = 'login.html';
}

const container = document.getElementById('listingsContainer');
const campusFilter = document.getElementById('campusFilter');
const categoryFilter = document.getElementById('categoryFilter');
const searchBtn = document.getElementById('searchBtn');

// Load listings on page load
loadListings();

searchBtn.addEventListener('click', loadListings);

async function loadListings() {
    const campus = campusFilter.value || '';
    const category = categoryFilter.value || '';
    
    container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-secondary);">Loading listings...</p>';

    try {
        let url = 'api/listings/list.php?status=active&page=1';
        if (campus) url += '&campus=' + encodeURIComponent(campus);
        if (category) url += '&category=' + encodeURIComponent(category);

        const response = await fetch(url);
        const result = await response.json();

        if (!result.success || !result.listings || result.listings.length === 0) {
            container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-secondary);">No listings found. Try different filters.</p>';
            return;
        }

        container.innerHTML = '';
        result.listings.forEach(listing => {
            const card = createListingCard(listing);
            container.appendChild(card);
        });
    } catch (error) {
        container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; padding: 40px; color: red;">Error loading listings: ' + error.message + '</p>';
    }
}

function createListingCard(listing) {
    const card = document.createElement('div');
    card.className = 'listing-card';
    
    const imageUrl = listing.images && listing.images.length > 0 
        ? listing.images[0].image_url 
        : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext x="50%" y="50%" text-anchor="middle" dy=".3em" fill="%23999"%3ENo Image%3C/text%3E%3C/svg%3E';
    
    const priceStr = listing.price ? '$' + parseFloat(listing.price).toFixed(2) : 'Negotiable';
    
    card.innerHTML = `
        <img src="${imageUrl}" alt="${listing.title}" class="listing-image">
        <div class="listing-info">
            <div class="listing-title">${listing.title}</div>
            <div class="listing-price">${priceStr}</div>
            <div class="listing-meta">
                <span>${listing.category}</span>
                <span>${listing.campus}</span>
            </div>
            <div class="listing-description">${listing.description}</div>
            <div class="seller-info">
                <img src="${listing.avatar || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ccircle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23ccc%22/%3E%3C/svg%3E'}" alt="" class="seller-avatar">
                <span>${listing.username}</span>
            </div>
        </div>
    `;
    
    card.addEventListener('click', () => {
        window.location.href = 'listing-detail.html?id=' + listing.id;
    });
    
    return card;
}
