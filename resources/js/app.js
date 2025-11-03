import '../css/app.css'
import Alpine from 'alpinejs'
import axios from 'axios'

// Make Alpine available globally
window.Alpine = Alpine

// Make axios available globally
window.axios = axios

// Configure axios defaults
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

// Get CSRF token if available
let token = document.head.querySelector('meta[name="csrf-token"]')
if (token) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content
}

// Start Alpine
Alpine.start()

// Log framework info
console.log(
  '%c VTPHP Framework ',
  'background: #3b82f6; color: white; font-size: 16px; padding: 4px 8px; border-radius: 4px;'
)
console.log(
  '%c Powered by Vite + Tailwind CSS + Alpine.js ',
  'background: #10b981; color: white; font-size: 12px; padding: 2px 6px; border-radius: 4px;'
)
