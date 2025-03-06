<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Define the page props interface
interface PageProps {
    auth: {
        user: any | null;
    };
    [key: string]: any;
}

// Get page props for auth status
const page = usePage<PageProps>();

// Endpoint options
const endpoints = [{ id: 'flights' as const, name: 'Flights', description: 'Search for real-time flight data' }];

// Type for endpoint IDs
type EndpointId = (typeof endpoints)[number]['id'];

// Selected endpoint - default is flights
const selectedEndpoint = ref<EndpointId>('flights');
const searchQuery = ref('');
const isLoading = ref(false);
const searchResults = ref<any>(null);
const error = ref<string | null>(null);

// Helper functions for formatting data
const formatDateTime = (dateTimeString: string) => {
    if (!dateTimeString) return '';
    try {
        const date = new Date(dateTimeString);
        return date.toLocaleString();
    } catch (e) {
        return dateTimeString;
    }
};

const formatFlightStatus = (status: string) => {
    if (!status) return '';
    // Capitalize first letter and make rest lowercase
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
};

const getDisplayTitle = (result: any) => {
    if (!result) return 'Unknown';

    // For flights
    if (result.flight) {
        const flightNum = result.flight.iata || result.flight.icao || result.flight.number;
        const airlineName = result.airline?.name || '';
        return `${airlineName} ${flightNum}`;
    }

    // For airports
    if (result.airport_name) {
        const code = result.iata_code || result.icao_code || '';
        const location = result.country_name ? `, ${result.country_name}` : '';
        return `${result.airport_name} (${code})${location}`;
    }

    // For airlines
    if (result.airline_name) {
        const code = result.iata_code || result.icao_code || '';
        return `${result.airline_name} (${code})`;
    }

    // Fallback
    return result.name || 'Unknown';
};

// Computed properties
const currentEndpoint = computed(() => {
    return endpoints.find((endpoint) => endpoint.id === selectedEndpoint.value);
});

// Search placeholder based on selected endpoint
const searchPlaceholder = computed(() => {
    return 'Enter flight number (e.g., BA123)';
});

// Search function
const performSearch = async () => {
    if (!searchQuery.value.trim()) return;

    isLoading.value = true;
    error.value = null;
    searchResults.value = null;

    try {
        // Build the query parameters
        let params = new URLSearchParams();

        // Add search parameters for flights
        if (searchQuery.value.includes('-')) {
            const [dep, arr] = searchQuery.value.split('-');
            params.append('dep_iata', dep.trim());
            params.append('arr_iata', arr.trim());
        } else {
            // Check if it's a number only or alphanumeric (IATA code + number)
            if (/^\d+$/.test(searchQuery.value.trim())) {
                params.append('flight_number', searchQuery.value.trim());
            } else {
                params.append('flight_iata', searchQuery.value.trim());
            }
        }

        // Add a debug parameter to bypass cache and get more details
        params.append('debug', 'true');

        try {
            // First try the Laravel API proxy
            console.log('Trying Laravel API proxy for flights');
            console.log('Request parameters:', params.toString());
            const response = await fetch(`/api/aviation/flights?${params.toString()}`);

            // Check if response is OK
            if (!response.ok) {
                console.error('API response not OK:', response.status, response.statusText);
                throw new Error(`Server error (${response.status}): ${response.statusText}`);
            }

            const data = await response.json();
            console.log('API response received:', {
                pagination: data.pagination,
                dataLength: data.data?.length || 0,
            });

            if (data.error) {
                error.value = data.error.message || 'An error occurred while fetching data';

                // Show a more user-friendly message for specific API error codes
                if (data.error.code === 'function_access_restricted' || data.error.code === 103) {
                    error.value =
                        'This feature is not available in the free tier of Aviationstack API. Please upgrade to a paid plan to access this endpoint.';
                } else if (data.error.code === 'database_error') {
                    error.value = 'The Aviationstack database is currently experiencing issues. Please try again later.';
                } else if (data.error.code === 'usage_limit_reached' || data.error.code === 104) {
                    error.value = 'The monthly API request limit has been reached. Please try again next month.';
                } else if (data.error.code === 'no_results') {
                    error.value = 'No results found matching your search criteria. Please try a different search term.';
                }

                if (data.error.details) {
                    console.error('API Error Details:', data.error.details);
                }
                if (data.error.code) {
                    error.value += ` (Error code: ${data.error.code})`;
                }
            } else {
                searchResults.value = data;

                // Check if we got empty results
                if (data.data && data.data.length === 0) {
                    error.value = 'No results found matching your search criteria. Please try a different search term.';
                    searchResults.value = null;
                }
            }
        } catch (proxyError) {
            console.error('Proxy route error:', proxyError);
            error.value = 'An error occurred while fetching data. Please try again.';
        }
    } catch (err) {
        console.error('Frontend Error:', err);
        error.value = 'An error occurred while fetching data. Please try again.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <Head title="Flight Status App">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-gradient-to-b from-blue-50 to-white dark:from-gray-900 dark:to-gray-800 dark:text-white">
        <!-- Header -->
        <header class="bg-white shadow-sm dark:bg-gray-800">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-8 w-8 text-blue-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                            ></path>
                        </svg>
                        <h1 class="ml-2 text-xl font-bold text-gray-900 dark:text-white">Flight Status</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <template v-if="page.props.auth?.user">
                            <Link
                                :href="route('dashboard')"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            >
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link
                                :href="route('login')"
                                class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            >
                                Log in
                            </Link>
                            <Link :href="route('register')" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Register
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white sm:text-4xl">Real-time Flight Information</h2>
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
                    Get up-to-date information about flights, including status, delays, and gate information.
                </p>

                <!-- Free tier limitations notice -->
                <div class="mx-auto mt-4 max-w-3xl rounded-md bg-blue-50 p-3 dark:bg-blue-900/30">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <strong>Free Tier Information:</strong> This application uses the Aviationstack API free tier, which has a limit of 100
                        requests per month.
                    </p>
                </div>
            </div>

            <!-- Search Section -->
            <div class="mx-auto mt-10 max-w-3xl">
                <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                    <div class="p-6">
                        <!-- Search Input -->
                        <div>
                            <label for="search" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Search Flights</label>
                            <div class="flex">
                                <input
                                    type="text"
                                    id="search"
                                    v-model="searchQuery"
                                    placeholder="Enter flight number (e.g., BA123) or route (e.g., LHR-JFK)"
                                    class="block w-full flex-1 rounded-l-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                                    @keyup.enter="performSearch"
                                />
                                <button
                                    type="button"
                                    @click="performSearch"
                                    class="inline-flex items-center rounded-r-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    :disabled="isLoading"
                                >
                                    <span v-if="isLoading">
                                        <svg
                                            class="-ml-1 mr-2 h-4 w-4 animate-spin text-white"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                        Searching...
                                    </span>
                                    <span v-else>Search</span>
                                </button>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Enter a flight number (e.g., BA123) or a route (e.g., LHR-JFK) to search for flight information.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <div v-if="searchResults || error" class="mt-6 overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                    <div class="p-6">
                        <!-- Error Message -->
                        <div v-if="error" class="rounded-md bg-red-50 p-4 dark:bg-red-900/30">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-5 w-5 text-red-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <div class="ml-3 flex-grow">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Error</h3>
                                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                        <p>{{ error }}</p>
                                        <div class="mt-4">
                                            <button
                                                type="button"
                                                @click="performSearch"
                                                class="inline-flex items-center rounded-md border border-transparent bg-red-100 px-4 py-2 text-sm font-medium text-red-800 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-900 dark:text-red-100 dark:hover:bg-red-800"
                                            >
                                                Try Again
                                            </button>
                                            <button
                                                type="button"
                                                @click="
                                                    selectedEndpoint = 'flights';
                                                    searchQuery = '';
                                                "
                                                class="ml-3 inline-flex items-center rounded-md border border-transparent bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                                            >
                                                Reset Search
                                            </button>
                                        </div>
                                        <p class="mt-2 text-xs italic">
                                            Note: Free API access has limitations on the number of requests (100/month). This application only
                                            displays endpoints available in the free tier.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <div v-if="searchResults && !error">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Results</h3>
                            <div class="mt-4 space-y-4">
                                <div
                                    v-for="result in searchResults.data"
                                    :key="result.id || result.flight?.iata || result.airport_id || result.airline_id || Math.random()"
                                    class="rounded-md bg-gray-100 p-4 dark:bg-gray-700"
                                >
                                    <h4 class="text-md font-semibold text-gray-800 dark:text-white">
                                        {{ getDisplayTitle(result) }}
                                    </h4>
                                    <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                        <!-- Flight information -->
                                        <li v-if="result.flight_date"><strong>Flight Date:</strong> {{ result.flight_date }}</li>
                                        <li v-if="result.flight_status"><strong>Status:</strong> {{ formatFlightStatus(result.flight_status) }}</li>
                                        <li v-if="result.departure?.airport">
                                            <strong>Departure:</strong> {{ result.departure.airport }} ({{ result.departure.iata }})
                                            <div v-if="result.departure.scheduled" class="ml-4 text-xs">
                                                <div><strong>Scheduled:</strong> {{ formatDateTime(result.departure.scheduled) }}</div>
                                                <div v-if="result.departure.actual">
                                                    <strong>Actual:</strong> {{ formatDateTime(result.departure.actual) }}
                                                </div>
                                                <div v-if="result.departure.delay"><strong>Delay:</strong> {{ result.departure.delay }} minutes</div>
                                            </div>
                                        </li>
                                        <li v-if="result.arrival?.airport">
                                            <strong>Arrival:</strong> {{ result.arrival.airport }} ({{ result.arrival.iata }})
                                            <div v-if="result.arrival.scheduled" class="ml-4 text-xs">
                                                <div><strong>Scheduled:</strong> {{ formatDateTime(result.arrival.scheduled) }}</div>
                                                <div v-if="result.arrival.actual">
                                                    <strong>Actual:</strong> {{ formatDateTime(result.arrival.actual) }}
                                                </div>
                                                <div v-if="result.arrival.delay"><strong>Delay:</strong> {{ result.arrival.delay }} minutes</div>
                                            </div>
                                        </li>
                                        <li v-if="result.airline?.name">
                                            <strong>Airline:</strong> {{ result.airline.name }} ({{ result.airline.iata || result.airline.icao }})
                                        </li>
                                        <li v-if="result.flight?.number">
                                            <strong>Flight Number:</strong> {{ result.flight.iata || result.flight.icao || result.flight.number }}
                                        </li>

                                        <!-- Airport information -->
                                        <li v-if="result.airport_name">
                                            <strong>Airport:</strong> {{ result.airport_name }}
                                            <span v-if="result.iata_code || result.icao_code" class="ml-1">
                                                (<span v-if="result.iata_code">IATA: {{ result.iata_code }}</span>
                                                <span v-if="result.iata_code && result.icao_code">, </span>
                                                <span v-if="result.icao_code">ICAO: {{ result.icao_code }}</span
                                                >)
                                            </span>
                                        </li>
                                        <li v-if="result.country_name"><strong>Location:</strong> {{ result.country_name }}</li>
                                        <li v-if="result.latitude && result.longitude">
                                            <strong>Coordinates:</strong> {{ result.latitude }}, {{ result.longitude }}
                                        </li>
                                        <li v-if="result.timezone"><strong>Timezone:</strong> {{ result.timezone }}</li>

                                        <!-- Airline information -->
                                        <li v-if="result.airline_name"><strong>Airline:</strong> {{ result.airline_name }}</li>
                                        <li v-if="result.iata_code && !result.airport_name"><strong>IATA:</strong> {{ result.iata_code }}</li>
                                        <li v-if="result.icao_code && !result.airport_name"><strong>ICAO:</strong> {{ result.icao_code }}</li>
                                        <li v-if="result.fleet_size"><strong>Fleet Size:</strong> {{ result.fleet_size }}</li>
                                        <li v-if="result.status"><strong>Status:</strong> {{ result.status }}</li>

                                        <li v-if="result.description"><strong>Description:</strong> {{ result.description }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Information Features</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-300">Get comprehensive real-time flight information and status updates</p>
            </div>

            <div class="mt-10 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                    <div class="p-6">
                        <div class="flex h-12 w-12 items-center justify-center rounded-md bg-blue-500 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Real-time Flight Status</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">
                            Get up-to-date information about flight status, including delays, gate information, and estimated arrival times.
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                    <div class="p-6">
                        <div class="flex h-12 w-12 items-center justify-center rounded-md bg-blue-500 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                                />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Route Information</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">
                            Search flights by route (e.g., LHR-JFK) to find all available flights between airports.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-gray-800">
                    <div class="p-6">
                        <div class="flex h-12 w-12 items-center justify-center rounded-md bg-blue-500 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Flight Schedules</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">
                            View detailed flight schedules including departure and arrival times, delays, and gate information.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-12 bg-white shadow-inner dark:bg-gray-800">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between md:flex-row">
                    <div class="flex items-center">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6 text-blue-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                            ></path>
                        </svg>
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Flight Status App</span>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Developed by Md Yaqub Ajgori</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
