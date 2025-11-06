#!/bin/bash
# =======================================================
# COMMON FUNCTIONS FOR PROXMOX INSTALLATION SCRIPTS
# =======================================================
# Description: Utility functions for colors, links, and formatting

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Icons/Emojis
CHECKMARK="✅"
ERROR="❌"
WARNING="⚠️"
INFO="ℹ️"
ROCKET="🚀"
GEAR="🔧"
LINK="🔗"

# Print colored messages
print_success() {
    echo -e "${GREEN}${CHECKMARK} $1${NC}"
}

print_error() {
    echo -e "${RED}${ERROR} $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}${WARNING} $1${NC}"
}

print_info() {
    echo -e "${BLUE}${INFO} $1${NC}"
}

print_header() {
    echo -e "${BOLD}${BLUE}"
    echo "=========================================="
    echo "    $1"
    echo "=========================================="
    echo -e "${NC}"
}

print_step() {
    echo -e "${CYAN}${ROCKET} Step $1: $2${NC}"
}

print_config() {
    echo -e "${PURPLE}${GEAR} $1${NC}"
}

# Create clickable link
create_link() {
    local url="$1"
    local text="${2:-$url}"
    echo -e "${LINK} \033]8;;${url}\033\\${text}\033]8;;\033\\"
}

# Create highlighted link with color
create_colored_link() {
    local url="$1"
    local text="${2:-$url}"
    local color="${3:-$GREEN}"
    echo -e "${color}${LINK} \033]8;;${url}\033\\${text}\033]8;;\033\\${NC}"
}

# Progress bar simulation
show_progress() {
    local duration=$1
    local message="$2"
    echo -n "$message"
    for ((i=0; i<=duration; i++)); do
        echo -n "."
        sleep 0.1
    done
    echo " Done!"
}

# Section divider
section_divider() {
    echo -e "${BLUE}================================================${NC}"
}

# Create a box around text
print_box() {
    local text="$1"
    local color="${2:-$BLUE}"
    local length=${#text}
    local border=$(printf "%-${length}s" | tr ' ' '=')

    echo -e "${color}"
    echo "╔═${border}═╗"
    echo "║ ${text} ║"
    echo "╚═${border}═╝"
    echo -e "${NC}"
}

# Print service status
print_service_status() {
    local service="$1"
    local url="$2"
    local status="$3"

    if [ "$status" = "running" ] || [ "$status" = "active" ]; then
        print_success "$service is running"
        if [ -n "$url" ]; then
            create_colored_link "$url" "Access $service" "$GREEN"
        fi
    else
        print_error "$service is not running"
    fi
}

# Installation summary
print_installation_summary() {
    local main_url="$1"
    local username="$2"

    print_box "PROXMOX INSTALLATION COMPLETED" "$GREEN"
    echo
    print_success "All installation steps completed successfully!"
    echo
    create_colored_link "$main_url" "Access Proxmox Web Interface" "$BOLD$GREEN"
    print_config "Login username: $username"
    echo
    section_divider
}

# Service links summary
print_services_summary() {
    local main_ip="$1"
    local vscode_ip="$2"
    local vscode_port="$3"
    local go2rtc_ip="$4"
    local go2rtc_port="$5"

    print_header "AVAILABLE SERVICES"

    echo -e "${WHITE}Main Services:${NC}"
    create_colored_link "https://$main_ip" "Proxmox Dashboard" "$GREEN"
    create_colored_link "https://$main_ip:81" "VS Code Web" "$BLUE"
    create_colored_link "https://$main_ip:82" "Camera Stream" "$PURPLE"

    echo
    echo -e "${WHITE}Direct Access:${NC}"
    if [ -n "$vscode_ip" ] && [ -n "$vscode_port" ]; then
        create_colored_link "http://$vscode_ip:$vscode_port" "VS Code Direct" "$CYAN"
    fi
    if [ -n "$go2rtc_ip" ] && [ -n "$go2rtc_port" ]; then
        create_colored_link "http://$go2rtc_ip:$go2rtc_port" "go2rtc Direct" "$YELLOW"
    fi

    section_divider
}

# Error handling
handle_error() {
    local error_message="$1"
    local exit_code="${2:-1}"

    print_error "$error_message"
    print_warning "Installation failed. Check the logs above for details."
    exit $exit_code
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Wait with progress
wait_with_progress() {
    local seconds="$1"
    local message="${2:-Waiting}"

    echo -n "$message"
    for ((i=1; i<=seconds; i++)); do
        echo -n "."
        sleep 1
    done
    echo " Done!"
}

# Validate URL accessibility
validate_url() {
    local url="$1"
    local timeout="${2:-5}"

    if curl -s --connect-timeout $timeout "$url" >/dev/null; then
        return 0
    else
        return 1
    fi
}

# Print environment info
print_env_info() {
    local env_file="$1"

    print_header "ENVIRONMENT CONFIGURATION"
    if [ -f "$env_file" ]; then
        print_success "Environment file found: $env_file"
        # Don't print sensitive information, just confirm it's loaded
        print_info "Configuration loaded successfully"
    else
        print_error "Environment file not found: $env_file"
    fi
}
