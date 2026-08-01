/* 
  FARMFLEET CLIENT APPLICATION LOGIC (Tailwind CSS SPA Version)
  Fully integrated with SQLite DB and PHP REST APIs.
*/

// ==========================================================================
// 1. STATE MANAGEMENT
// ==========================================================================
window.user = null;
window.currentRole = 'farmer'; // farmer, owner, admin
window.activeLanguage = 'en'; // en, ta
window.activeMarketMode = 'rent'; // rent, sell
window.selectedCategory = 'All';
window.searchRadius = 100;
window.operatorFilter = 'all';
window.ownerActiveTab = 'dashboard';
window.adminChartPeriod = 'weekly';

// Active listings cache
window.listingsCache = [];
window.selectedListing = null;

// ==========================================================================
// 2. COORDINATES FOR LIVE GPS MAP
// ==========================================================================
const LOCATION_COORDINATES = {
  'Gandhipuram, Coimbatore': { x: 35, y: 45 },
  'Peelamedu, Coimbatore': { x: 50, y: 50 },
  'Saravanampatti, Coimbatore': { x: 55, y: 30 },
  'Thondamuthur, Coimbatore': { x: 15, y: 60 },
  'Modakurichi, Erode': { x: 80, y: 75 },
  'Bhavani, Erode': { x: 85, y: 40 },
  'Perundurai, Erode': { x: 70, y: 55 },
  'Pollachi, Coimbatore': { x: 25, y: 85 },
  'Singanallur, Coimbatore': { x: 48, y: 58 },
  'Thudiyalur, Coimbatore': { x: 30, y: 28 },
  'Gobichettipalayam, Erode': { x: 75, y: 25 },
  'Erode South': { x: 78, y: 62 }
};

function getCoordsForLocation(locName) {
  // Direct match
  for (let key in LOCATION_COORDINATES) {
    if (locName.toLowerCase().includes(key.toLowerCase().split(',')[0])) {
      return LOCATION_COORDINATES[key];
    }
  }
  // Fallback random coord in safe central bounds
  const seed = locName.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const x = 30 + (seed % 40);
  const y = 30 + ((seed * 7) % 40);
  return { x, y };
}

// ==========================================================================
// 3. TOAST SYSTEM
// ==========================================================================
window.showToast = function(message, type = 'success') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'toast-enter bg-white shadow-2xl border border-gray-100 rounded-2xl p-4 flex items-center gap-3 w-80 relative overflow-hidden transform transition-all duration-300';
  
  // Custom type styling
  let icon = '🔔';
  let barColor = 'bg-green-500';
  if (type === 'error') {
    icon = '❌';
    barColor = 'bg-red-500';
  } else if (type === 'info') {
    icon = 'ℹ️';
    barColor = 'bg-blue-500';
  } else if (type === 'warning') {
    icon = '⚠️';
    barColor = 'bg-amber-500';
  }

  toast.innerHTML = `
    <div class="absolute top-0 left-0 w-1.5 h-full ${barColor}"></div>
    <div class="text-2xl">${icon}</div>
    <div class="flex-1">
      <p class="text-sm font-extrabold text-gray-900">${message}</p>
    </div>
    <button class="text-gray-400 hover:text-gray-600 font-bold text-xs" onclick="this.parentElement.remove()">✕</button>
  `;

  container.appendChild(toast);
  
  // Trigger animation frame
  setTimeout(() => {
    toast.classList.remove('toast-enter');
    toast.classList.add('toast-active');
  }, 10);

  // Auto remove
  setTimeout(() => {
    toast.classList.remove('toast-active');
    toast.classList.add('toast-enter');
    setTimeout(() => toast.remove(), 400);
  }, 4000);
};

// ==========================================================================
// 4. LANGUAGE TRANSLATION ENGINE
// ==========================================================================
window.toggleTamil = function() {
  window.activeLanguage = window.activeLanguage === 'en' ? 'ta' : 'en';
  translatePage();
};

function translatePage() {
  const lang = window.activeLanguage;
  
  // Translate elements with data-en/data-ta
  document.querySelectorAll('[data-en]').forEach(el => {
    const val = el.getAttribute(`data-${lang}`);
    if (val) el.textContent = val;
  });

  // Translate placeholders
  document.querySelectorAll('input[placeholder]').forEach(el => {
    const enVal = el.getAttribute('placeholder');
    // If not saved, keep backup
    if (!el.hasAttribute('data-placeholder-backup')) {
      el.setAttribute('data-placeholder-backup', enVal);
    }
    
    // Check if phone or name inputs specifically
    if (el.id === 'userName') {
      el.placeholder = lang === 'en' ? 'e.g., Ramesh Kumar' : 'எ.கா., ரமேஷ் குமார்';
    } else if (el.id === 'userPhone') {
      el.placeholder = lang === 'en' ? 'Mobile Number' : 'மொபைல் எண்';
    } else if (el.id === 'searchInput') {
      el.placeholder = lang === 'en' ? "Search 'Farmtrac', 'Drone'..." : "தேடுக 'டிராக்டர்', 'ட்ரோன்'...";
    }
  });

  // Language button update
  const langBtn = document.getElementById('lang-btn');
  if (langBtn) {
    langBtn.textContent = lang === 'en' ? 'தமிழ்' : 'English';
  }

  // Reload lists to render dynamically in target language
  renderEquipment();
}

// ==========================================================================
// 5. VIEW ROUTER
// ==========================================================================
window.navigateTo = function(viewId) {
  const views = ['section-login', 'section-role', 'section-farmer', 'section-owner', 'section-admin'];
  views.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      if (id === viewId) {
        el.classList.remove('hide-section');
      } else {
        el.classList.add('hide-section');
      }
    }
  });
};

window.switchRoleToSelect = function() {
  navigateTo('section-role');
};

// ==========================================================================
// 6. AUTHENTICATION HANDLERS
// ==========================================================================
window.handleLogin = function() {
  const name = document.getElementById('userName').value.trim();
  const phone = document.getElementById('userPhone').value.trim();

  if (!name || !phone) {
    showToast('Name and phone number are required!', 'error');
    return;
  }

  showToast('Authenticating...', 'info');

  fetch('api.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, phone, role: 'farmer' }) // Standard role is farmer on setup
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.user = data.user;
      window.currentRole = data.user.role;
      
      // Update displays
      document.getElementById('display-name').textContent = window.user.name;
      document.getElementById('owner-name-display').textContent = window.user.name;
      
      showToast(`Welcome back, ${window.user.name}!`);
      
      // Direct routing if admin
      if (window.user.role === 'admin') {
        navigateTo('section-admin');
        loadAdminDashboard();
      } else {
        navigateTo('section-role');
      }
      
      // Setup language defaults
      translatePage();
    } else {
      showToast(data.error || 'Authentication failed!', 'error');
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Error communicating with backend', 'error');
  });
};

window.selectRole = function(role) {
  window.currentRole = role;
  showToast(`Switching to ${role === 'farmer' ? 'Farmer Console' : 'Owner Console'}...`, 'info');

  // Update role on DB
  fetch('api.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: window.user.phone, name: window.user.name, role: role })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.user = data.user;
      if (role === 'farmer') {
        navigateTo('section-farmer');
        loadFarmerDashboard();
      } else {
        navigateTo('section-owner');
        loadOwnerDashboard();
      }
    } else {
      showToast(data.error || 'Failed to switch workspace', 'error');
    }
  })
  .catch(err => console.error(err));
};

window.promptAdminPin = function() {
  document.getElementById('admin-pin-modal').classList.remove('hide-section');
};

window.closeAdminPin = function() {
  document.getElementById('admin-pin-modal').classList.add('hide-section');
  document.getElementById('admin-pin-input').value = '';
};

window.verifyAdminPin = function() {
  const pin = document.getElementById('admin-pin-input').value;
  if (pin === '0000') {
    closeAdminPin();
    showToast('Access Granted to Admin Panel!');
    
    // Set role to admin on backend
    fetch('api.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: '9876543212', role: 'admin' }) // Admin phone seed
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.user = data.user;
        window.currentRole = 'admin';
        navigateTo('section-admin');
        loadAdminDashboard();
      }
    });
  } else {
    showToast('Invalid Admin PIN! Try 0000.', 'error');
  }
};

window.logout = function() {
  showToast('Signing out...', 'info');
  fetch('api.php?action=logout')
  .then(() => {
    window.user = null;
    document.getElementById('userName').value = '';
    document.getElementById('userPhone').value = '';
    navigateTo('section-login');
  });
};

// ==========================================================================
// 7. FARMER PORTAL & MARKETPLACE GRID
// ==========================================================================
function loadFarmerDashboard() {
  // Dynamic categories seed list
  const categories = [
    { name: 'All', icon: '🌾' },
    { name: 'Tractors', icon: '🚜' },
    { name: 'Harvesters', icon: '🌾' },
    { name: 'JCB', icon: '🏗️' },
    { name: 'Agri Drones', icon: '🛸' },
    { name: 'Borewell Rigs', icon: '🕳️' },
    { name: 'Sprayers', icon: '💨' }
  ];
  
  const catBtnContainer = document.getElementById('category-buttons');
  if (catBtnContainer) {
    catBtnContainer.innerHTML = categories.map(cat => `
      <button onclick="setCategoryFilter('${cat.name}')" class="px-5 py-2.5 rounded-xl font-extrabold text-xs transition flex items-center gap-2 border whitespace-nowrap ${window.selectedCategory === cat.name ? 'bg-[#115E33] text-white border-[#115E33]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#115E33]' }">
        <span>${cat.icon}</span> ${cat.name}
      </button>
    `).join('');
  }

  fetchListings();
  fetchBookingsCount();
}

window.setCategoryFilter = function(catName) {
  window.selectedCategory = catName;
  loadFarmerDashboard(); // updates active design styling
};

window.setMarketMode = function(mode) {
  window.activeMarketMode = mode;
  const rentBtn = document.getElementById('mode-btn-rent');
  const sellBtn = document.getElementById('mode-btn-sell');
  
  if (mode === 'rent') {
    rentBtn.className = 'toggle-btn active px-8 py-2 rounded-lg font-extrabold text-sm transition-all';
    sellBtn.className = 'toggle-btn inactive px-8 py-2 rounded-lg font-extrabold text-sm transition-all';
  } else {
    rentBtn.className = 'toggle-btn inactive px-8 py-2 rounded-lg font-extrabold text-sm transition-all';
    sellBtn.className = 'toggle-btn active px-8 py-2 rounded-lg font-extrabold text-sm transition-all';
  }
  
  fetchListings();
};

window.updateRadius = function(val) {
  window.searchRadius = parseInt(val);
  document.getElementById('radius-val').textContent = `${val} km`;
  renderEquipment();
};

window.updateOperatorFilter = function(val) {
  window.operatorFilter = val;
  renderEquipment();
};

window.updateFarmerLocation = function(val) {
  if (!val) return;
  showToast(`Field Location: ${val}`);
  if (window.user) {
    window.user.city = val;
    fetch('api.php?action=update_profile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: window.user.name,
        area: val,
        address: window.user.address || 'Coimbatore, Tamil Nadu',
        avatar: window.user.avatar || ''
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.user = data.user;
      }
    });
  }
  fetchListings();
};

function fetchListings() {
  const mode = window.activeMarketMode;
  let typeQuery = '';
  if (window.selectedCategory !== 'All') {
    // Map human category names to db type abbreviations
    const mapping = {
      'Tractors': 'tractor',
      'Harvesters': 'harvester',
      'JCB': 'earthmover',
      'Agri Drones': 'sprayer', // Sprayer/drones
      'Borewell Rigs': 'plough',
      'Sprayers': 'sprayer'
    };
    typeQuery = mapping[window.selectedCategory] || '';
  }

  fetch(`api.php?action=get_listings&mode=${mode}&type=${typeQuery}`)
  .then(res => res.json())
  .then(data => {
    window.listingsCache = data;
    renderEquipment();
  })
  .catch(err => console.error(err));
}

window.renderEquipment = function() {
  const grid = document.getElementById('equipment-grid');
  const searchVal = document.getElementById('searchInput').value.toLowerCase();
  const countSummary = document.getElementById('count-summary');
  const lang = window.activeLanguage;

  if (!grid) return;

  let filtered = window.listingsCache.filter(item => {
    // 1. Search text
    const matchesSearch = item.name.toLowerCase().includes(searchVal) || 
                          item.name_ta.toLowerCase().includes(searchVal) ||
                          item.location.toLowerCase().includes(searchVal);
                          
    // 2. Radius check
    const matchesRadius = item.distance <= window.searchRadius;

    // 3. Operator requirement check
    let matchesOperator = true;
    const hasDriverTag = item.tags.some(tag => tag.toLowerCase().includes('driver') || tag.toLowerCase().includes('pilot'));
    if (window.operatorFilter === 'driver') {
      matchesOperator = hasDriverTag;
    } else if (window.operatorFilter === 'self') {
      matchesOperator = !hasDriverTag;
    }

    return matchesSearch && matchesRadius && matchesOperator;
  });

  // Render
  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="col-span-full py-16 text-center">
        <span class="text-5xl block mb-4">🌾</span>
        <h4 class="text-xl font-extrabold text-gray-900" data-en="No equipment found matching criteria." data-ta="தேடலுக்குரிய கருவிகள் எதுவும் கிடைக்கவில்லை.">No equipment found matching criteria.</h4>
        <p class="text-sm text-gray-500 font-bold mt-2" data-en="Try widening your filters or search radius." data-ta="வடிப்பான்கள் அல்லது ஆரத்தை மாற்றியமைக்கவும்.">Try widening your filters or search radius.</p>
      </div>
    `;
    if (countSummary) countSummary.textContent = lang === 'en' ? 'Showing 0 items' : '0 கருவிகள் காண்பிக்கப்படுகின்றன';
    return;
  }

  if (countSummary) {
    countSummary.textContent = lang === 'en' ? `Showing ${filtered.length} vehicles` : `${filtered.length} வாகனங்கள் காண்பிக்கப்படுகின்றன`;
  }

  grid.innerHTML = filtered.map(item => {
    const isRent = item.price !== null;
    const priceText = isRent 
      ? `₹${item.price} <span class="text-xs font-bold text-gray-400">/ ${item.price_unit}</span>`
      : `₹${numberWithCommas(item.sale_price)}`;
      
    const nameText = lang === 'en' ? item.name : item.name_ta;
    
    // Check tags to display first two
    const tagsToDisplay = lang === 'en' ? item.tags : item.tagsTa;
    const tagsHtml = tagsToDisplay.slice(0, 2).map(tag => `
      <span class="text-[10px] font-extrabold uppercase tracking-wider bg-green-50 text-[#115E33] px-2.5 py-1 rounded-full">${tag}</span>
    `).join('');

    return `
      <div class="bg-white rounded-[2rem] border border-gray-100 hover:border-[#115E33] shadow-sm hover:shadow-xl transition duration-300 overflow-hidden flex flex-col group cursor-pointer" onclick="openBookingModal(${item.id})">
        <div class="h-48 bg-gray-100 relative overflow-hidden">
          <img src="${item.image}" alt="${nameText}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" onerror="this.onerror=null; this.src='image_cdc718.jpg';">
          <span class="absolute top-4 right-4 bg-white/90 backdrop-blur text-xs font-extrabold px-3 py-1.5 rounded-full shadow-md text-gray-800">
            📍 ${item.distance} km
          </span>
        </div>
        <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
          <div>
            <div class="flex items-center justify-between gap-2 mb-2">
              <div class="flex gap-1.5">${tagsHtml}</div>
              <div class="flex items-center gap-0.5 text-amber-500 font-extrabold text-xs">⭐ ${item.rating}</div>
            </div>
            <h4 class="font-extrabold text-lg text-gray-900 group-hover:text-[#115E33] transition">${nameText}</h4>
            <p class="text-xs text-gray-500 font-bold mt-1.5">📍 ${item.location}</p>
          </div>
          <div class="flex items-end justify-between border-t border-gray-50 pt-4 mt-auto">
            <div>
              <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">${isRent ? 'Rental Rate' : 'Selling Price'}</p>
              <h5 class="text-xl font-extrabold text-gray-900">${priceText}</h5>
            </div>
            <button class="bg-[#115E33] hover:bg-green-800 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl shadow-lg transition">
              ${isRent ? 'Rent ➔' : 'Buy ➔'}
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Helper formatting
function numberWithCommas(x) {
  if (!x) return '0';
  return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function fetchBookingsCount() {
  fetch('api.php?action=get_dashboard_data')
  .then(res => res.json())
  .then(data => {
    if (data.rentals || data.purchases) {
      const total = (data.rentals ? data.rentals.length : 0) + (data.purchases ? data.purchases.length : 0);
      document.getElementById('farmer-bookings-badge').textContent = total;
    }
  });
}

// ==========================================================================
// 8. DETAIL & BOOKING/CHECKOUT MULTI-STEP MODAL
// ==========================================================================
window.openBookingModal = function(listingId) {
  window.selectedListing = window.listingsCache.find(item => item.id === listingId);
  if (!window.selectedListing) return;

  const item = window.selectedListing;
  const lang = window.activeLanguage;

  // Set modal texts
  document.getElementById('modal-img').src = item.image;
  document.getElementById('modal-title').textContent = lang === 'en' ? item.name : item.name_ta;
  document.getElementById('modal-cat-badge').textContent = item.type.toUpperCase();
  
  const hasDriver = item.tags.some(tag => tag.toLowerCase().includes('driver') || tag.toLowerCase().includes('pilot'));
  document.getElementById('modal-operator-badge').textContent = hasDriver 
    ? (lang === 'en' ? 'Driver Included' : 'ஓட்டுநருடன்')
    : (lang === 'en' ? 'Self-Drive' : 'சுய-ஓட்டுநர்');

  document.getElementById('modal-owner-name').textContent = item.owner_name || 'Ramesh Kumar';
  document.getElementById('modal-owner-phone').textContent = `📞 +91 98765 4321${item.owner_id || 1}`;
  document.getElementById('modal-owner-rating').textContent = item.rating || '4.9';
  document.getElementById('modal-owner-review-text').textContent = item.details || 'Excellent vehicle in perfect working order.';

  // Build specifications list
  const specs = [
    { label: 'Horsepower', val: item.horsepower ? `${item.horsepower} HP` : 'N/A' },
    { label: 'Fuel Type', val: item.fuel_type || 'Diesel' },
    { label: 'Transmission', val: item.transmission || 'Manual' },
    { label: 'Usage Hours', val: item.hours_of_usage ? `${item.hours_of_usage} hrs` : '0 hrs' },
    { label: 'Crop Suitability', val: item.suitable_crop || 'General' }
  ];
  document.getElementById('modal-specs-grid').innerHTML = specs.map(s => `
    <div class="flex justify-between py-1.5 border-b border-gray-50"><span class="text-gray-400">${s.label}</span><span class="text-gray-900 font-extrabold">${s.val}</span></div>
  `).join('');

  // Live Status Telematics
  const randStatus = ['Idle', 'Working', 'Idle'][item.id % 3];
  const fieldWorkStatusEl = document.getElementById('modal-field-work-status');
  fieldWorkStatusEl.textContent = randStatus;
  fieldWorkStatusEl.className = randStatus === 'Idle' 
    ? 'text-xs font-extrabold px-3 py-1 rounded-full bg-green-100 text-green-800' 
    : 'text-xs font-extrabold px-3 py-1 rounded-full bg-red-100 text-red-800';

  document.getElementById('modal-exit-time').textContent = '2:15 PM';
  document.getElementById('modal-eta').textContent = `${Math.round(item.distance * 2.5)} mins`;

  // Pricing setup
  const isRent = item.price !== null;
  document.getElementById('modal-breakdown-title').textContent = isRent ? 'Rental Pricing' : 'Purchase Pricing';
  document.getElementById('modal-base-label').textContent = isRent 
    ? `Base Rate (₹ / ${item.price_unit})`
    : 'Vehicle Selling Price';
  
  const baseRate = isRent ? item.price : item.sale_price;
  document.getElementById('modal-base-rate-display').textContent = `₹${numberWithCommas(baseRate)}`;

  // Show/Hide duration input based on listing type
  const durationContainer = document.getElementById('duration-container');
  if (isRent) {
    durationContainer.classList.remove('hidden');
    document.getElementById('booking-duration').value = 1;
  } else {
    durationContainer.classList.add('hidden');
  }

  // Distance Transit Fee
  document.getElementById('modal-distance-text').textContent = `${item.distance} km`;
  const transitFee = Math.round(item.distance * 20);
  document.getElementById('modal-transit-fee').textContent = `₹${transitFee}`;

  // Next KYC button configurations
  const btnNextKyc = document.getElementById('modal-btn-next-kyc');
  
  // Restricted checkout check if role is owner
  if (window.currentRole === 'owner') {
    btnNextKyc.disabled = true;
    btnNextKyc.className = 'w-full bg-gray-300 text-gray-500 font-extrabold py-4 rounded-xl mt-4 cursor-not-allowed text-xs uppercase';
    btnNextKyc.textContent = lang === 'en' ? 'Only Farmers Can Book/Buy' : 'விவசாயிகள் மட்டுமே முன்பதிவு செய்ய முடியும்';
  } else {
    btnNextKyc.disabled = false;
    btnNextKyc.className = 'w-full bg-[#115E33] text-white font-extrabold py-4 rounded-xl mt-4 hover:bg-green-800 transition text-sm shadow-lg uppercase tracking-wide';
    btnNextKyc.textContent = lang === 'en' ? 'Proceed to Identity Verification ➔' : 'அடையாள சரிபார்ப்புக்கு செல்லவும் ➔';
  }

  calculateTotal();

  // Reset steps
  goToStep(1);
  
  document.getElementById('booking-modal').classList.remove('hide-section');
};

window.closeBookingModal = function() {
  document.getElementById('booking-modal').classList.add('hide-section');
};

window.calculateTotal = function() {
  const item = window.selectedListing;
  if (!item) return;

  const isRent = item.price !== null;
  const baseRate = isRent ? item.price : item.sale_price;
  
  let duration = 1;
  if (isRent) {
    duration = parseInt(document.getElementById('booking-duration').value) || 1;
  }

  const transitFee = Math.round(item.distance * 20);
  
  // Platform fee is 5% for Rent, or calculated dynamically from metrics/settings
  const platformFee = Math.round(baseRate * duration * 0.05);

  const grandTotal = (baseRate * duration) + transitFee + platformFee;

  document.getElementById('modal-commission').textContent = `₹${platformFee}`;
  document.getElementById('modal-total').textContent = `₹${numberWithCommas(grandTotal)}`;
  
  // Update Payment Step 3 variables
  document.getElementById('payment-eq-name').textContent = item.name;
  document.getElementById('payment-grand-total').textContent = `₹${numberWithCommas(grandTotal)}`;
  document.getElementById('payment-full-btn-amt').textContent = `₹${numberWithCommas(grandTotal)}`;
  document.getElementById('payment-adv-btn-amt').textContent = `₹${numberWithCommas(Math.round(grandTotal * 0.2))}`;
};

window.goToStep = function(stepNum) {
  const s1 = document.getElementById('booking-step-1');
  const s2 = document.getElementById('booking-step-2');
  const s3 = document.getElementById('booking-step-3');

  s1.classList.add('hide-section');
  s2.classList.add('hide-section');
  s3.classList.add('hide-section');

  if (stepNum === 1) s1.classList.remove('hide-section');
  if (stepNum === 2) s2.classList.remove('hide-section');
  if (stepNum === 3) s3.classList.remove('hide-section');
};

window.verifyKYC = function() {
  const aadhaar = document.getElementById('kyc-id').value.trim();
  const otp = document.getElementById('kyc-otp').value.trim();

  if (aadhaar.length !== 12) {
    showToast('Please enter a valid 12-digit Aadhaar number!', 'error');
    return;
  }
  if (otp !== '1234') {
    showToast('Incorrect verification OTP! Use demo code: 1234', 'error');
    return;
  }

  showToast('Aadhaar verified successfully!');
  goToStep(3);
};

window.processPayment = function(payType) {
  const item = window.selectedListing;
  if (!item) return;

  const isRent = item.price !== null;
  const baseRate = isRent ? item.price : item.sale_price;
  
  let duration = 1;
  if (isRent) {
    duration = parseInt(document.getElementById('booking-duration').value) || 1;
  }
  const transitFee = Math.round(item.distance * 20);
  const platformFee = Math.round(baseRate * duration * 0.05);
  const grandTotal = (baseRate * duration) + transitFee + platformFee;

  const actualPaymentAmount = payType === 'full' ? grandTotal : Math.round(grandTotal * 0.2);

  showToast('Processing secure transaction...', 'info');

  const bookingPayload = {
    listingId: item.id,
    type: isRent ? 'rent' : 'buy',
    startDate: 'Today',
    endDate: isRent ? `+${duration} days` : null,
    amount: grandTotal,
    paymentMethod: payType === 'full' ? 'UPI Full' : 'UPI 20% Advance',
    paymentDetails: `UTR-${Math.floor(100000000000 + Math.random() * 900000000000)}`
  };

  fetch('api.php?action=book_listing', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(bookingPayload)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('Booking successfully placed & verified!');
      closeBookingModal();
      openFarmerBookings(); // open list to view status
      loadFarmerDashboard(); // refresh counter
    } else {
      showToast(data.error || 'Booking transaction failed!', 'error');
    }
  })
  .catch(err => console.error(err));
};

window.openFarmerBookings = function() {
  showToast('Loading active bookings history...', 'info');
  
  fetch('api.php?action=get_dashboard_data')
  .then(res => res.json())
  .then(data => {
    const listEl = document.getElementById('farmer-bookings-list');
    if (!listEl) return;

    const rentals = data.rentals || [];
    const purchases = data.purchases || [];
    const all = [...rentals, ...purchases];

    if (all.length === 0) {
      listEl.innerHTML = `
        <div class="py-8 text-center text-gray-500 font-bold">
          No bookings placed yet.
        </div>
      `;
    } else {
      listEl.innerHTML = all.map(b => {
        let statusBadgeClass = 'bg-amber-100 text-amber-800';
        if (b.status === 'approved' || b.status === 'completed') statusBadgeClass = 'bg-green-100 text-green-800';
        if (b.status === 'rejected') statusBadgeClass = 'bg-red-100 text-red-800';

        const isRent = b.type === 'rent';

        return `
          <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <img src="${b.image}" class="w-16 h-16 rounded-2xl object-cover shrink-0" onerror="this.onerror=null; this.src='image_cdc718.jpg';">
              <div>
                <h4 class="font-extrabold text-gray-900">${b.listing_name}</h4>
                <p class="text-xs text-gray-500 font-bold mt-1">🗓️ Period: ${b.start_date} ${b.end_date ? `to ${b.end_date}` : ''}</p>
                <p class="text-xs text-gray-400 font-bold mt-0.5">👤 Contact: ${b.owner_name || b.seller_name} (+91 ${b.owner_phone || b.seller_phone})</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-xs font-extrabold uppercase px-3 py-1 rounded-full ${statusBadgeClass}">${b.status}</span>
              <h5 class="text-lg font-extrabold text-gray-900 mt-2">₹${numberWithCommas(b.amount)}</h5>
            </div>
          </div>
        `;
      }).join('');
    }

    document.getElementById('farmer-bookings-modal').classList.remove('hide-section');
  })
  .catch(err => console.error(err));
};

// ==========================================================================
// 9. OWNER CONSOLE HANDLERS & SIMULATION MAP
// ==========================================================================
window.switchOwnerTab = function(tabName) {
  window.ownerActiveTab = tabName;

  const btnDashboard = document.getElementById('tab-btn-dashboard');
  const btnFleet = document.getElementById('tab-btn-fleet');
  const btnMap = document.getElementById('tab-btn-map');

  const contentDashboard = document.getElementById('owner-tab-dashboard');
  const contentFleet = document.getElementById('owner-tab-fleet');
  const contentMap = document.getElementById('owner-tab-map');

  // Clear styles
  [btnDashboard, btnFleet, btnMap].forEach(btn => {
    btn.className = 'w-full flex items-center gap-4 px-6 py-4 text-gray-400 hover:text-white hover:bg-gray-800 rounded-2xl font-extrabold';
  });
  [contentDashboard, contentFleet, contentMap].forEach(div => div.classList.add('hide-section'));

  // Active state
  if (tabName === 'dashboard') {
    btnDashboard.className = 'w-full flex items-center gap-4 px-6 py-4 bg-gradient-to-r from-[#115E33] to-[#047857] text-white rounded-2xl font-extrabold shadow-lg';
    contentDashboard.classList.remove('hide-section');
    loadOwnerDashboard();
  } else if (tabName === 'fleet') {
    btnFleet.className = 'w-full flex items-center gap-4 px-6 py-4 bg-gradient-to-r from-[#115E33] to-[#047857] text-white rounded-2xl font-extrabold shadow-lg';
    contentFleet.classList.remove('hide-section');
    loadOwnerDashboard();
  } else if (tabName === 'map') {
    btnMap.className = 'w-full flex items-center gap-4 px-6 py-4 bg-gradient-to-r from-[#115E33] to-[#047857] text-white rounded-2xl font-extrabold shadow-lg';
    contentMap.classList.remove('hide-section');
    renderOwnerMap();
  }
};

window.loadOwnerDashboard = function() {
  fetch('api.php?action=owner_data')
  .then(res => res.json())
  .then(data => {
    // Stats updating
    document.getElementById('total-earnings-display').textContent = `₹${numberWithCommas(Math.round(data.totalEarnings))}`;
    document.getElementById('active-fleet-count').textContent = data.listings ? data.listings.length : 0;
    
    const activeJobs = data.requests ? data.requests.filter(r => r.status === 'approved' || r.status === 'completed').length : 0;
    document.getElementById('active-jobs-count').textContent = activeJobs;

    // Requests layout rendering
    const reqContainer = document.getElementById('requests-container');
    if (reqContainer) {
      const pendingReqs = data.requests ? data.requests.filter(r => r.status === 'pending') : [];
      if (pendingReqs.length === 0) {
        reqContainer.innerHTML = `
          <div class="py-6 text-center text-gray-500 font-bold">
            No pending customer requests at this time.
          </div>
        `;
      } else {
        reqContainer.innerHTML = pendingReqs.map(r => `
          <div class="bg-gray-50 p-6 rounded-3xl border border-gray-100 flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <img src="${r.farmer_avatar || 'https://images.unsplash.com/photo-1542909168-82c3e7fdca5c?auto=format&fit=crop&w=100&h=100&q=80'}" class="w-12 h-12 rounded-full object-cover shrink-0">
              <div>
                <h4 class="font-extrabold text-gray-900">${r.farmer_name}</h4>
                <p class="text-xs text-gray-500 font-bold mt-1">Requested: <span class="text-[#115E33]">${r.equipment_name}</span></p>
                <p class="text-xs text-gray-400 font-bold mt-0.5">🗓️ Schedule: ${r.start_date} ${r.end_date ? `to ${r.end_date}` : ''}</p>
              </div>
            </div>
            <div class="flex gap-2">
              <button onclick="respondRequest(${r.id}, 'rejected')" class="bg-red-50 text-red-600 border border-red-100 font-extrabold text-xs px-4 py-2.5 rounded-xl hover:bg-red-100 transition">Decline</button>
              <button onclick="respondRequest(${r.id}, 'approved')" class="bg-[#115E33] text-white font-extrabold text-xs px-4 py-2.5 rounded-xl hover:bg-green-800 transition shadow-md">Accept Request</button>
            </div>
          </div>
        `).join('');
      }
    }

    // Fleet Directory table
    const fleetTbody = document.getElementById('owner-fleet-tbody');
    if (fleetTbody) {
      const fleetList = data.listings || [];
      if (fleetList.length === 0) {
        fleetTbody.innerHTML = `
          <tr>
            <td colspan="4" class="py-8 text-center text-gray-500 font-bold">
              No registered fleet equipment found. Register your first machine!
            </td>
          </tr>
        `;
      } else {
        fleetTbody.innerHTML = fleetList.map(item => {
          const isRent = item.price !== null;
          const statusText = item.status === 'approved' ? 'Live & Active' : 'Pending Verification';
          const badgeClass = item.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800';

          return `
            <tr class="hover:bg-gray-50/50 transition">
              <td class="px-6 py-4 flex items-center gap-3">
                <img src="${item.image}" class="w-12 h-12 rounded-xl object-cover shrink-0" onerror="this.onerror=null; this.src='image_cdc718.jpg';">
                <div>
                  <h4 class="font-extrabold text-gray-900">${item.name}</h4>
                  <p class="text-xs text-gray-400 font-bold mt-0.5">📍 Location: ${item.location}</p>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="text-xs font-extrabold uppercase px-2.5 py-1 rounded-full ${badgeClass}">${statusText}</span>
              </td>
              <td class="px-6 py-4">
                <p class="text-xs text-gray-500 font-bold">⭐ ${item.rating || '5.0'} (${item.reviews_count || 0} reviews)</p>
              </td>
              <td class="px-6 py-4 text-right">
                <h5 class="font-extrabold text-gray-900">${isRent ? `₹${item.price} / ${item.price_unit}` : `₹${numberWithCommas(item.sale_price)}`}</h5>
              </td>
            </tr>
          `;
        }).join('');
      }
    }
  });
};

window.respondRequest = function(requestId, status) {
  showToast('Updating booking status...', 'info');
  fetch('api.php?action=respond_request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ requestId, status })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(status === 'approved' ? 'Customer request accepted!' : 'Booking request declined.');
      loadOwnerDashboard();
    } else {
      showToast(data.error || 'Failed to update status', 'error');
    }
  });
};

window.openAddFleetModal = function() {
  document.getElementById('add-fleet-modal').classList.remove('hide-section');
};

window.closeAddFleetModal = function() {
  document.getElementById('add-fleet-modal').classList.add('hide-section');
  document.getElementById('add-fleet-form').reset();
};

window.handleListingTypeChange = function() {
  const type = document.getElementById('new-eq-list-type').value;
  const labelPrice = document.getElementById('add-price-label');
  const unitContainer = document.getElementById('add-unit-container');
  const operatorContainer = document.getElementById('operator-add-container');

  if (type === 'sell') {
    labelPrice.textContent = 'Selling Price (₹)';
    unitContainer.classList.add('hidden');
    operatorContainer.classList.add('hidden');
  } else {
    labelPrice.textContent = 'Rental Rate (₹)';
    unitContainer.classList.remove('hidden');
    operatorContainer.classList.remove('hidden');
  }
};

window.handleCategoryChange = function() {
  const cat = document.getElementById('new-eq-cat').value;
  const capacityContainer = document.getElementById('capacity-container');
  if (cat === 'Borewell Rigs') {
    capacityContainer.classList.remove('hidden');
  } else {
    capacityContainer.classList.add('hidden');
  }
};

window.handleAddFleet = function(event) {
  event.preventDefault();

  const name = document.getElementById('new-eq-name').value;
  const category = document.getElementById('new-eq-cat').value;
  const listType = document.getElementById('new-eq-list-type').value;
  const location = document.getElementById('new-eq-loc').value;
  const operator = document.getElementById('new-eq-operator').value === 'true';
  const priceInput = parseFloat(document.getElementById('new-eq-price').value);
  const unit = document.getElementById('new-eq-unit').value;
  const capacity = document.getElementById('new-eq-capacity').value;

  const payload = {
    name: name,
    type: mapCategoryToAbbr(category),
    price: listType === 'rent' ? priceInput : null,
    priceUnit: listType === 'rent' ? unit.replace('/', '').trim() : null,
    salePrice: listType === 'sell' ? priceInput : null,
    location: location,
    distance: 4.5, // Seed mock distance
    suitableCrop: capacity ? `Borewell Depth Max: ${capacity}` : 'Any',
    details: `${category} listed in excellent condition in Coimbatore.`,
    tags: operator ? ['With Driver', 'Fuel Included'] : ['Self-Drive'],
    tagsTa: operator ? ['டிரைவருடன்', 'எரிபொருள் உட்பட'] : ['சுய ஓட்டுதல்']
  };

  showToast('Saving listing...', 'info');

  fetch('api.php?action=add_listing', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast('Listing successfully onboarded and activated!');
      closeAddFleetModal();
      
      if (window.currentRole === 'owner') {
        switchOwnerTab('dashboard');
      } else if (window.currentRole === 'admin') {
        loadAdminDashboard();
      } else {
        loadFarmerDashboard();
      }
    } else {
      showToast(data.error || 'Failed to save listing', 'error');
    }
  });
};

function mapCategoryToAbbr(cat) {
  const mapping = {
    'Tractors': 'tractor',
    'Harvesters': 'harvester',
    'JCB': 'earthmover',
    'Agri Drones': 'sprayer',
    'Borewell Rigs': 'plough',
    'Sprayers': 'sprayer'
  };
  return mapping[cat] || 'tractor';
}

window.renderOwnerMap = function() {
  const container = document.getElementById('map-container');
  if (!container) return;

  // Clear other than bg overlay
  const overlays = container.querySelectorAll('.map-pin');
  overlays.forEach(o => o.remove());

  showToast('Locating vehicles on telematics map...', 'info');

  fetch('api.php?action=owner_data')
  .then(res => res.json())
  .then(data => {
    const list = data.listings || [];
    
    if (list.length === 0) {
      showToast('No active vehicles to locate.', 'warning');
      return;
    }

    list.forEach((item, index) => {
      const coords = getCoordsForLocation(item.location);
      const randStatus = ['idle', 'working', 'broken'][index % 3];
      const pinClass = randStatus === 'idle' ? 'pin-idle' : (randStatus === 'working' ? 'pin-working' : 'pin-broken');
      
      const pin = document.createElement('div');
      pin.className = `map-pin absolute ${pinClass}`;
      pin.style.left = `${coords.x}%`;
      pin.style.top = `${coords.y}%`;
      
      pin.innerHTML = `
        <div class="relative group">
          <span class="text-3xl filter hover:scale-125 transition drop-shadow-lg">📍</span>
          <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 bg-gray-900/95 backdrop-blur text-white text-xs rounded-xl py-2 px-3 whitespace-nowrap opacity-0 group-hover:opacity-100 transition shadow-2xl pointer-events-none z-30 border border-gray-800">
            <p class="font-extrabold text-white">${item.name}</p>
            <p class="text-[10px] text-cyan-400 mt-0.5 font-bold uppercase tracking-wider">Status: ${randStatus.toUpperCase()}</p>
          </div>
        </div>
      `;
      
      container.appendChild(pin);
    });
  });
};

// ==========================================================================
// 10. SUPER ADMIN CONSOLE & CHART GRAPH
// ==========================================================================
window.loadAdminDashboard = function() {
  fetch('api.php?action=admin_metrics')
  .then(res => res.json())
  .then(data => {
    // Stats
    document.getElementById('admin-revenue-display').textContent = `₹${numberWithCommas(data.totalRevenue)}`;
    document.getElementById('admin-rentals-display').textContent = data.transactions ? data.transactions.filter(t => t.type === 'rent').length : 0;
    document.getElementById('admin-pending-display').textContent = data.pendingQueue ? data.pendingQueue.length : 0;
    
    // Disputes simulation
    document.getElementById('admin-disputes-display').textContent = 1; // Default mock disputes

    // Onboarding tables
    const tbody = document.getElementById('admin-equipment-tbody');
    if (tbody) {
      const list = data.pendingQueue || [];
      document.getElementById('admin-showing-text').textContent = `Showing ${list.length} of ${list.length} records`;
      
      if (list.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="py-6 text-center text-gray-500 font-bold">
              No new equipment pending onboarding verification.
            </td>
          </tr>
        `;
      } else {
        tbody.innerHTML = list.map(item => `
          <tr class="hover:bg-[#1A1D24] transition">
            <td class="py-4 font-bold text-white">${item.owner_name || 'Renter'}</td>
            <td class="py-4 text-gray-300 font-bold">${item.name}</td>
            <td class="py-4 text-gray-400 font-bold">${item.location}</td>
            <td class="py-4 text-cyan-400 font-bold">📄 View KYC</td>
            <td class="py-4">
              <span class="bg-amber-900/40 text-amber-400 border border-amber-900/60 text-xs font-bold px-2 py-0.5 rounded">Pending Approval</span>
            </td>
            <td class="py-4 text-center flex justify-center gap-2">
              <button onclick="verifyListing(${item.id}, 'rejected')" class="bg-red-950/40 text-red-400 border border-red-950 font-bold text-xs px-2.5 py-1.5 rounded hover:bg-red-900/40 transition">Reject</button>
              <button onclick="verifyListing(${item.id}, 'approved')" class="bg-green-950/40 text-green-400 border border-green-950 font-bold text-xs px-2.5 py-1.5 rounded hover:bg-green-900/40 transition">Approve</button>
            </td>
          </tr>
        `).join('');
      }
    }

    // Action center prioritizing queue
    const actContainer = document.getElementById('admin-action-center');
    if (actContainer) {
      let actionsHtml = '';
      if (data.pendingQueue && data.pendingQueue.length > 0) {
        actionsHtml += data.pendingQueue.map(item => `
          <div class="admin-card p-4 rounded-xl border border-amber-900/20 bg-amber-900/5 flex items-center justify-between">
            <div>
              <p class="text-xs text-amber-400 font-bold uppercase tracking-wider">KYC Verification Needed</p>
              <h5 class="text-sm font-extrabold text-white mt-1">${item.name}</h5>
              <p class="text-[10px] text-gray-400 font-bold mt-0.5">Submitted by ${item.owner_name}</p>
            </div>
            <button onclick="verifyListing(${item.id}, 'approved')" class="bg-amber-400 text-black font-bold text-[10px] px-3 py-1.5 rounded-lg shadow-lg hover:bg-amber-300">Approve</button>
          </div>
        `).join('');
      }
      
      // Default dispute action task
      actionsHtml += `
        <div class="admin-card p-4 rounded-xl border border-red-900/20 bg-red-900/5 flex items-center justify-between">
          <div>
            <p class="text-xs text-red-400 font-bold uppercase tracking-wider">Active Dispute #781</p>
            <h5 class="text-sm font-extrabold text-white mt-1">John Deere 5050D Delay</h5>
            <p class="text-[10px] text-gray-400 font-bold mt-0.5">Ramesh Kumar vs. Senthil Raja</p>
          </div>
          <button onclick="showToast('Loading dispute logs...', 'info')" class="bg-red-400 text-black font-bold text-[10px] px-3 py-1.5 rounded-lg shadow-lg hover:bg-red-300">Resolve</button>
        </div>
      `;
      actContainer.innerHTML = actionsHtml;
    }

    // Drawing Chart.js Revenue Analytics
    window.adminTransactions = data.transactions || [];
    updateAdminChartData();
  });
};

window.verifyListing = function(listingId, status) {
  showToast('Updating listing status...', 'info');
  fetch('api.php?action=admin_verify_listing', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ listingId, status })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(status === 'approved' ? 'Listing approved and active!' : 'Listing rejected.');
      loadAdminDashboard();
    } else {
      showToast(data.error || 'Failed to verify listing', 'error');
    }
  });
};

window.toggleAdminChart = function(period) {
  window.adminChartPeriod = period;
  
  const dailyBtn = document.getElementById('btn-chart-daily');
  const weeklyBtn = document.getElementById('btn-chart-weekly');

  if (period === 'daily') {
    dailyBtn.className = 'px-4 py-1.5 text-xs font-bold bg-cyan-400 text-black rounded shadow-[0_0_10px_rgba(6,182,212,0.4)]';
    weeklyBtn.className = 'px-4 py-1.5 text-xs font-bold text-gray-400 hover:text-white rounded';
  } else {
    weeklyBtn.className = 'px-4 py-1.5 text-xs font-bold bg-cyan-400 text-black rounded shadow-[0_0_10px_rgba(6,182,212,0.4)]';
    dailyBtn.className = 'px-4 py-1.5 text-xs font-bold text-gray-400 hover:text-white rounded';
  }

  updateAdminChartData();
};

function updateAdminChartData() {
  let labels = [];
  let dataPoints = [];

  let dbTotal = 0;
  if (window.adminTransactions) {
    window.adminTransactions.forEach(t => {
      if (t.status === 'completed' || t.status === 'approved') {
        dbTotal += parseFloat(t.amount);
      }
    });
  }

  // 10% platform commission slice
  const commissionSlice = Math.round(dbTotal * 0.1);

  if (window.adminChartPeriod === 'daily') {
    labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    // Add dynamic commissions to the latest day (Sunday)
    dataPoints = [12000, 18500, 15000, 22000, 29000, 35000, 28000 + commissionSlice];
  } else {
    labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
    // Add dynamic total transaction volumes to Week 5
    dataPoints = [110000, 145000, 185000, 240000, 280000 + dbTotal];
  }

  initAdminChart(labels, dataPoints);
}

let adminChartInstance = null;
function initAdminChart(labels, dataPoints) {
  const ctx = document.getElementById('adminRevenueChart');
  if (!ctx) return;
  if (adminChartInstance) {
    adminChartInstance.destroy();
  }
  adminChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Revenue (₹)',
        data: dataPoints,
        borderColor: '#22d3ee', // Cyan-400
        backgroundColor: 'rgba(34, 211, 238, 0.05)',
        tension: 0.4,
        fill: true,
        borderWidth: 2,
        pointBackgroundColor: '#22d3ee'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { 
          grid: { color: '#1e293b' }, 
          ticks: { color: '#94a3b8', font: { weight: 'bold' } } 
        },
        x: { 
          grid: { display: false }, 
          ticks: { color: '#94a3b8', font: { weight: 'bold' } } 
        }
      }
    }
  });
}

// ==========================================================================
// 11. INITIALIZATION ON DOCUMENT LOAD
// ==========================================================================
document.addEventListener('DOMContentLoaded', () => {
  // Pre-load default initial session
  if (window.__INITIAL_SESSION__ && window.__INITIAL_SESSION__.loggedIn) {
    window.user = window.__INITIAL_SESSION__.user;
    window.currentRole = window.user.role;

    document.getElementById('display-name').textContent = window.user.name;
    document.getElementById('owner-name-display').textContent = window.user.name;

    if (window.user.role === 'admin') {
      navigateTo('section-admin');
      loadAdminDashboard();
    } else if (window.user.role === 'owner') {
      navigateTo('section-owner');
      loadOwnerDashboard();
    } else {
      navigateTo('section-farmer');
      loadFarmerDashboard();
    }
  } else {
    navigateTo('section-login');
  }

  // Setup language translation defaults
  translatePage();
});
