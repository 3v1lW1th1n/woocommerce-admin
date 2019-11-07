#!/bin/bash

ZIP_FILE='woocommerce-admin.zip';

while [ $# -gt 0 ]; do
	if [[ $1 == '-f' || $1 == '--features' ]]; then
		export WC_ADMIN_ADDITIONAL_FEATURES="$2"
	fi
	if [[ $1 == '-s' || $1 == '--slug' ]]; then
		ZIP_FILE="woocommerce-admin-$2.zip";
	fi
	shift
done

# Exit if any command fails.
set -e

# Change to the expected directory.
cd "$(dirname "$0")"
cd ..

# Enable nicer messaging for build status.
BLUE_BOLD='\033[1;34m';
GREEN_BOLD='\033[1;32m';
RED_BOLD='\033[1;31m';
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';
error () {
	echo -e "\n${RED_BOLD}$1${COLOR_RESET}\n"
}
status () {
	echo -e "\n${BLUE_BOLD}$1${COLOR_RESET}\n"
}
success () {
	echo -e "\n${GREEN_BOLD}$1${COLOR_RESET}\n"
}
warning () {
	echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}

status "💃 Time to release WooCommerce Admin 🕺"

# Make sure there are no changes in the working tree. Release builds should be
# traceable to a particular commit and reliably reproducible. (This is not
# totally true at the moment because we download nightly vendor scripts).
changed=
if ! git diff --exit-code > /dev/null; then
	changed="file(s) modified"
elif ! git diff --cached --exit-code > /dev/null; then
	changed="file(s) staged"
fi
if [ ! -z "$changed" ]; then
	git status
	error "ERROR: Cannot build plugin zip with dirty working tree. ☝️
       Commit your changes and try again."
	exit 1
fi

# Do a dry run of the repository reset. Prompting the user for a list of all
# files that will be removed should prevent them from losing important files!
status "Resetting the repository to pristine condition. ✨"
git clean -xdf --dry-run
warning "🚨 About to delete everything above! Is this okay? 🚨"
echo -n "[y]es/[N]o: "
read answer
if [ "$answer" != "${answer#[Yy]}" ]; then
	# Remove ignored files to reset repository to pristine condition. Previous
	# test ensures that changed files abort the plugin build.
	status "Cleaning working directory... 🛀"
	git clean -xdf
else
	error "Fair enough; aborting. Tidy up your repo and try again. 🙂"
	exit 1
fi

# Install PHP dependencies
status "Gathering PHP dependencies... 🐿️"
composer install --no-dev

# Build the Core files.
status "Generating a Core build... 👷‍♀️"
WC_ADMIN_PHASE=core npm run build

# Make a Github release.
status "Starting a Github release... 👷‍♀️"
./bin/github-deploy.sh

# Build the plugin.
status "Generating the plugin build... 👷‍♀️"
WC_ADMIN_PHASE=plugin npm run build

build_files=$(ls dist/*/*.{js,css})

# Generate the plugin zip file.
status "Creating archive... 🎁"
zip -r ${ZIP_FILE} \
	woocommerce-admin.php \
	uninstall.php \
	includes/ \
	images/ \
	$build_files \
	languages/woocommerce-admin.pot \
	languages/woocommerce-admin.php \
	readme.txt \
	src/ \
	vendor/

success "Done. You've built WooCommerce Admin! 🎉 "
