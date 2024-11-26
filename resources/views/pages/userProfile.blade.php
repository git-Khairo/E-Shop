<x-dashboardLayout>
        <div class="mx-auto max-w-screen-lg px-4 2xl:px-0">
          <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl md:mb-6">Profile Overview</h2>
          <div class="flex justify-evenly items-center gap-6 border-b border-t border-gray-200 py-4 dark:border-gray-700 md:py-8 lg:grid-cols-4 xl:gap-16">
            <div>
              <svg class="mb-2 h-8 w-8 text-gray-400 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 4h1.5L9 16m0 0h8m-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8.5-3h9.25L19 7H7.312" />
              </svg>
              <h3 class="mb-2 text-gray-500 dark:text-gray-400">Orders made</h3>
              <span class="flex items-center text-2xl font-bold text-gray-900 dark:text-white"
                >{{ $orderCount }}
              </span>
            </div>
            <div>
              <svg class="mb-2 h-8 w-8 text-gray-400 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9h13a5 5 0 0 1 0 10H7M3 9l4-4M3 9l4 4" />
              </svg>
              <h3 class="mb-2 text-gray-500 dark:text-gray-400">Product returns</h3>
              <span class="flex items-center text-2xl font-bold text-gray-900 dark:text-white"
                >0
              </span>
            </div>
          </div>
          <div class="py-4 md:py-8">
            <div class="mb-4 grid gap-4 sm:grid-cols-2 sm:gap-8 lg:gap-16">
              <div class="space-y-4">
                <div class="flex space-x-4">
                  <img class="h-16 w-16 rounded-lg" src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/avatars/helene-engels.png" alt="Helene avatar" />
                  <div>
                    <button type="button" data-modal-target="accountInformationModal2" data-modal-toggle="accountInformationModal2" class="inline-flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-teal-600 hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 sm:w-auto">
                        <svg class="-ms-0.5 me-1.5 h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m14.304 4.844 2.852 2.852M7 7H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1v-4.5m2.409-9.91a2.017 2.017 0 0 1 0 2.853l-6.844 6.844L8 14l.713-3.565 6.844-6.844a2.015 2.015 0 0 1 2.852 0Z"></path>
                        </svg>
                        Edit your data
                      </button>
                    <h2 class="flex items-center text-xl font-bold leading-none text-gray-900 dark:text-white sm:text-2xl">{{ $user->username }}</h2>
                  </div>
                </div>
                <dl class="">
                  <dt class="font-semibold text-gray-900 dark:text-white">Email Address</dt>
                  <dd class="text-gray-500 dark:text-gray-400">{{ $user->email }}</dd>
                </dl>
                <dl>
                  <dt class="font-semibold text-gray-900 dark:text-white">Home Address</dt>
                  <dd class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                    <svg class="hidden h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500 lg:inline" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                    2 Miles Drive, NJ 071, New York, United States of America
                  </dd>
                </dl>
                <dl>
                  <dt class="font-semibold text-gray-900 dark:text-white">Delivery Address</dt>
                  <dd class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                    <svg class="hidden h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500 lg:inline" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h6l2 4m-8-4v8m0-8V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v9h2m8 0H9m4 0h2m4 0h2v-4m0 0h-5m3.5 5.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Zm-10 0a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" />
                    </svg>
                    9th St. PATH Station, New York, United States of America
                  </dd>
                </dl>
              </div>
              <div class="space-y-4">
                <dl>
                  <dt class="font-semibold text-gray-900 dark:text-white">Phone Number</dt>
                  <dd class="text-gray-500 dark:text-gray-400">+1234 567 890 / +12 345 678</dd>
                </dl>
                <dl>
                  <dt class="mb-1 font-semibold text-gray-900 dark:text-white">Payment Methods</dt>
                  <dd class="flex items-center space-x-4 text-gray-500 dark:text-gray-400">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                      <img class="h-4 w-auto dark:hidden" src="https://flowbite.s3.amazonaws.com/blocks/e-commerce/brand-logos/visa.svg" alt="" />
                      <img class="hidden h-4 w-auto dark:flex" src="https://flowbite.s3.amazonaws.com/blocks/e-commerce/brand-logos/visa-dark.svg" alt="" />
                    </div>
                    <div>
                      <div class="text-sm">
                        <p class="mb-0.5 font-medium text-gray-900 dark:text-white">Visa : #1234 4321 1234</p>
                        <p class="font-normal text-gray-500 dark:text-gray-400">Expiry 10/2024</p>
                      </div>
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
        <!-- Account Information Modal -->
        <div id="accountInformationModal2" tabindex="-1" aria-hidden="true" class="max-h-auto fixed left-0 right-0 top-0 z-50 hidden h-[calc(100%-1rem)] max-h-full w-full items-center justify-center overflow-y-auto overflow-x-hidden antialiased md:inset-0">
          <div class="max-h-auto relative max-h-full w-full max-w-lg p-4">
            <!-- Modal content -->
            <div class="relative rounded-lg bg-white shadow dark:bg-gray-800">
              <!-- Modal header -->
              <div class="flex items-center justify-between rounded-t border-b border-gray-200 p-4 dark:border-gray-700 md:p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Account Information</h3>
                <button type="button" class="ms-auto inline-flex h-8 w-8 items-center justify-center rounded-lg bg-transparent text-sm text-gray-400 hover:bg-gray-200 hover:text-gray-900 dark:hover:bg-gray-600 dark:hover:text-white" data-modal-toggle="accountInformationModal2">
                  <svg class="h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                  </svg>
                  <span class="sr-only">Close modal</span>
                </button>
              </div>
              <!-- Modal body -->
              <form class="p-4 md:p-5" action="{{ route('updateProfile') }}" method="post">
                @csrf
                @method('PUT')
                <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
      
                  <div class="col-span-2 sm:col-span-1">
                    <label for="full_name_info_modal" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"> Your Username* </label>
                    <input type="text" name="username" id="full_name_info_modal" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500" placeholder="{{ $user->username }}"/>
                  </div>
      
                  <div class="col-span-2 sm:col-span-1">
                    <label for="email_info_modal" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"> Your Email* </label>
                    <input type="text" name="email" id="email_info_modal" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500" placeholder="{{ $user->email }}"/>
                  </div>

                  <div class="col-span-2">
                    <label for="email_info_modal" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"> Your Password* </label>
                    <input type="password" name="password" id="email_info_modal" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500" placeholder="{{ $user->password }}"/>
                  </div>

                  <div class="col-span-2">
                    <label for="address_billing_modal" class="mb-2 block text-sm font-medium text-gray-900 dark:text-white"> Delivery Address* </label>
                    <textarea id="address_billing_modal" rows="4" class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500" placeholder="Enter here your address"></textarea>
                  </div>
                </div>
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700 md:pt-5">
                  <button type="submit" class="me-2 inline-flex items-center rounded-lg bg-primary-700 px-5 py-2.5 text-center text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Save information</button>
                  <button type="button" data-modal-toggle="accountInformationModal2" class="me-2 rounded-lg border border-gray-200 bg-white px-5 py-2.5 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </div>
</x-dashboardLayout>