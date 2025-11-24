use actix_web::{App, HttpResponse, HttpServer, middleware, web};
use actix_files::NamedFile;
use actix_cors::Cors;
use dotenv::dotenv;
use serde::{Deserialize, Serialize};
use std::env;
use std::path::PathBuf;

#[derive(Debug, Clone)]
struct ServerConfig {
    host: String,
    port: u16,
    workers: usize,
    log_level: String,
    max_connections: usize,
    keep_alive: u64,
    request_timeout: u64,
    cors_enabled: bool,
    cors_origins: String,
    server_name: String,
    url_vscode: String,
    url_comfyui: String,
    ip_proxmox_public: String,
}

impl ServerConfig {
    fn from_env() -> Self {
        ServerConfig {
            host: env::var("SERVER_HOST").unwrap_or_else(|_| "0.0.0.0".to_string()),
            port: env::var("SERVER_PORT").unwrap_or_else(|_| "8080".to_string()).parse().expect("SERVER_PORT must be a valid number"),
            workers: env::var("SERVER_WORKERS").unwrap_or_else(|_| "4".to_string()).parse().expect("SERVER_WORKERS must be a valid number"),
            log_level: env::var("SERVER_LOG_LEVEL").unwrap_or_else(|_| "info".to_string()),
            max_connections: env::var("SERVER_MAX_CONNECTIONS").unwrap_or_else(|_| "25000".to_string()).parse().expect("SERVER_MAX_CONNECTIONS must be a valid number"),
            keep_alive: env::var("SERVER_KEEP_ALIVE").unwrap_or_else(|_| "75".to_string()).parse().expect("SERVER_KEEP_ALIVE must be a valid number"),
            request_timeout: env::var("SERVER_REQUEST_TIMEOUT").unwrap_or_else(|_| "60".to_string()).parse().expect("SERVER_REQUEST_TIMEOUT must be a valid number"),
            cors_enabled: env::var("SERVER_CORS_ENABLED").unwrap_or_else(|_| "true".to_string()).parse().unwrap_or(true),
            cors_origins: env::var("SERVER_CORS_ORIGINS").unwrap_or_else(|_| "*".to_string()),
            server_name: env::var("SERVER_NAME").unwrap_or_else(|_| env::var("TAILSCALE_HOSTNAME").unwrap_or_else(|_| "Proxmox Server".to_string())),
            url_vscode: env::var("URL_VSCODE").unwrap_or_else(|_| "".to_string()),
            url_comfyui: env::var("URL_COMFYUI").unwrap_or_else(|_| "".to_string()),
            ip_proxmox_public: env::var("IP_PROXMOX_PUBLIC").unwrap_or_else(|_| "https://192.168.0.50:8006".to_string()),
        }
    }
}

#[derive(Serialize, Deserialize)]
struct ServerInfo {
    server_name: String,
    url_vscode: String,
    url_comfyui: String,
    ip_proxmox_public: String,
    server_host: String,
    server_port: u16,
}

#[derive(Serialize, Deserialize)]
struct CameraConfig {
    camera1_name: String,
    camera1_label: String,
    camera2_name: String,
    camera2_label: String,
    url_go2rtc: String,
}

// Route de base - sert le fichier index.html
async fn index() -> Result<NamedFile, std::io::Error> {
    let path: PathBuf = "./public/index.html".parse().unwrap();
    NamedFile::open(path)
}

// Route domotique - sert le fichier domotique.html
async fn domotique() -> Result<NamedFile, std::io::Error> {
    let path: PathBuf = "./public/domotique.html".parse().unwrap();
    NamedFile::open(path)
}

// Route API pour récupérer les informations du serveur
async fn api_server_info(config: web::Data<ServerConfig>) -> HttpResponse {
    let server_info = ServerInfo {
        server_name: config.server_name.clone(),
        url_vscode: config.url_vscode.clone(),
        url_comfyui: config.url_comfyui.clone(),
        ip_proxmox_public: config.ip_proxmox_public.clone(),
        server_host: config.host.clone(),
        server_port: config.port,
    };
    HttpResponse::Ok().json(server_info)
}

// Route API pour récupérer la configuration des caméras
async fn api_camera_config() -> HttpResponse {
    let camera_config = CameraConfig {
        camera1_name: env::var("CAMERA1_NAME").unwrap_or_else(|_| "tapo_camera1".to_string()),
        camera1_label: env::var("CAMERA1_LABEL").unwrap_or_else(|_| "Caméra 1".to_string()),
        camera2_name: env::var("CAMERA2_NAME").unwrap_or_else(|_| "tapo_camera2".to_string()),
        camera2_label: env::var("CAMERA2_LABEL").unwrap_or_else(|_| "Caméra 2".to_string()),
        url_go2rtc: env::var("URL_GO2RTC").unwrap_or_else(|_| "".to_string()),
    };
    HttpResponse::Ok().json(camera_config)
}


#[actix_web::main]
async fn main() -> std::io::Result<()> {
    // Charger les variables d'environnement depuis le fichier .env
    dotenv().ok();

    // Charger la configuration
    let config = ServerConfig::from_env();

    // Initialiser le logger
    unsafe {
        env::set_var("RUST_LOG", &config.log_level);
    }
    env_logger::init();

    let bind_address = format!("{}:{}", config.host, config.port);

    log::info!("🚀 Starting Proxmox Server...");
    log::info!("📡 Server address: http://{}", bind_address);
    log::info!("👷 Workers: {}", config.workers);
    log::info!("📊 Max connections: {}", config.max_connections);
    log::info!("⏱️  Keep alive: {}s", config.keep_alive);
    log::info!("🔒 CORS enabled: {}", config.cors_enabled);

    let config_data = web::Data::new(config.clone());
    let keep_alive = std::time::Duration::from_secs(config.keep_alive);

    // Démarrer le serveur HTTP
    HttpServer::new(move || {
        let cors = if config.cors_origins == "*" {
            Cors::permissive()
        } else {
            Cors::default()
                .allowed_origin(&config.cors_origins)
                .allowed_methods(vec!["GET", "POST"])
                .allowed_headers(vec![actix_web::http::header::CONTENT_TYPE])
                .max_age(3600)
        };

        App::new()
            .app_data(config_data.clone())
            // Middleware
            .wrap(cors)
            .wrap(middleware::Logger::default())
            .wrap(middleware::Compress::default())
            // Routes
            .route("/", web::get().to(index))
            .route("/domotique.html", web::get().to(domotique))
            .route("/api/server-info", web::get().to(api_server_info))
            .route("/api/camera-config", web::get().to(api_camera_config))
    })
    .workers(config.workers)
    .max_connections(config.max_connections)
    .keep_alive(keep_alive)
    .bind(&bind_address)?
    .run()
    .await
}
