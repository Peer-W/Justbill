// In-memory storage for demo - in production, use actual JSON files or a database

interface Product {
  id: string
  title: string
  description: string
  icon: string
  features: string[]
  redirectPath: string
  enabled: boolean
}

interface Stat {
  value: string
  label: string
}

interface Settings {
  customerPortalUrl: string
  companyEmail: string
  companyPhone: string
  stats: Stat[]
}

interface AdminCredentials {
  username: string
  password: string
}

// Default products
let products: Product[] = [
  {
    id: "webhosting",
    title: "Webhosting",
    description: "Betrouwbare en snelle webhosting voor al je websites.",
    icon: "Globe",
    features: ["Onbeperkte bandbreedte", "SSD-opslag", "Gratis SSL-certificaat", "Dagelijkse backups"],
    redirectPath: "webhosting",
    enabled: true,
  },
  {
    id: "vps",
    title: "VPS-diensten",
    description: "Krachtige virtuele private servers met volledige controle.",
    icon: "Server",
    features: ["Root-toegang", "Keuze uit Linux/Windows", "SSD NVMe-opslag", "Schaalbare resources"],
    redirectPath: "vps",
    enabled: true,
  },
  {
    id: "domeinen",
    title: "Domeinregistratie",
    description: "Registreer je perfecte domeinnaam vandaag nog.",
    icon: "HardDrive",
    features: ["100+ extensies beschikbaar", "Gratis DNS-beheer", "Domein privacy", "Eenvoudig verlengen"],
    redirectPath: "domeinen",
    enabled: true,
  },
  {
    id: "dns",
    title: "PW-DNS",
    description: "Professioneel DNS-beheer voor optimale prestaties.",
    icon: "Wifi",
    features: ["Anycast DNS", "DDoS-bescherming", "Snelle propagatie", "API-toegang"],
    redirectPath: "dns",
    enabled: true,
  },
]

let settings: Settings = {
  customerPortalUrl: "https://klanten.pw-services.nl",
  companyEmail: "info@pw-services.nl",
  companyPhone: "+31 (0) 6 12345678",
  stats: [
    { value: "99.9%", label: "Uptime" },
    { value: "24/7", label: "Support" },
    { value: "100+", label: "Klanten" },
    { value: "NL", label: "Datacenters" },
  ],
}

let adminCredentials: AdminCredentials = {
  username: "admin",
  password: "admin",
}

// Products
export async function getProducts(): Promise<Product[]> {
  return products
}

export async function addProduct(product: Omit<Product, "id">): Promise<Product> {
  const newProduct = {
    ...product,
    id: product.title.toLowerCase().replace(/\s+/g, "-") + "-" + Date.now(),
  }
  products.push(newProduct)
  return newProduct
}

export async function updateProduct(id: string, product: Partial<Product>): Promise<Product | null> {
  const index = products.findIndex((p) => p.id === id)
  if (index === -1) return null
  
  products[index] = { ...products[index], ...product }
  return products[index]
}

export async function deleteProduct(id: string): Promise<boolean> {
  const index = products.findIndex((p) => p.id === id)
  if (index === -1) return false
  
  products.splice(index, 1)
  return true
}

// Settings
export async function getSettings(): Promise<Settings> {
  return settings
}

export async function updateSettings(newSettings: Partial<Settings>): Promise<Settings> {
  settings = { ...settings, ...newSettings }
  return settings
}

// Admin Credentials
export async function getAdminCredentials(): Promise<AdminCredentials> {
  return adminCredentials
}

export async function updateAdminCredentials(credentials: AdminCredentials): Promise<AdminCredentials> {
  adminCredentials = { ...adminCredentials, ...credentials }
  return adminCredentials
}
