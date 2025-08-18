// Configuración del Frontend DonFactura
const CONFIG = {
    // URL base de la API
    API_BASE_URL: 'http://localhost:8000',
    
    // Rutas de archivos (deben coincidir con la API)
    STORAGE_PATHS: {
        certificates: '/storage/certificates/',
        xml_temp: '/storage/temp/',
        xml_generated: '/storage/generated/',
        logs: '/storage/logs/',
        pdfs: '/storage/pdfs/',
        uploads: '/storage/uploads/',
        xml: '/storage/xml/'
    },
    
    // Tipos de DTE disponibles
    DTE_TYPES: {
        33: 'Factura Electrónica',
        34: 'Factura Electrónica Exenta',
        39: 'Boleta Electrónica',
        41: 'Boleta de Honorarios Electrónica (BHE)',
        45: 'Factura de Compra Electrónica',
        56: 'Nota de Débito Electrónica',
        61: 'Nota de Crédito Electrónica'
    },
    
    // Formatos de PDF disponibles
    PDF_FORMATS: {
        carta: 'Formato Carta (21.5x27.9cm)',
        '80mm': 'Formato 80mm (Impresora Térmica)'
    },
    
    // Formas de pago
    PAYMENT_METHODS: {
        1: 'Contado',
        2: 'Crédito',
        3: 'Sin Costo'
    },
    
    // Unidades de medida comunes
    UNITS: [
        { value: 'UN', label: 'Unidad' },
        { value: 'KG', label: 'Kilogramo' },
        { value: 'LT', label: 'Litro' },
        { value: 'MT', label: 'Metro' },
        { value: 'M2', label: 'Metro Cuadrado' },
        { value: 'M3', label: 'Metro Cúbico' },
        { value: 'HRS', label: 'Horas' },
        { value: 'DIA', label: 'Día' },
        { value: 'MES', label: 'Mes' }
    ],
    
    // Comunas principales de Chile
    COMUNAS: [
        'SANTIAGO',
        'PROVIDENCIA',
        'LAS CONDES',
        'VITACURA',
        'ÑUÑOA',
        'LA REINA',
        'MAIPÚ',
        'PUDAHUEL',
        'QUILICURA',
        'VALPARAÍSO',
        'VIÑA DEL MAR',
        'CONCEPCIÓN',
        'TEMUCO',
        'VALDIVIA',
        'OSORNO',
        'PUERTO MONTT',
        'PUNTA ARENAS',
        'ANTOFAGASTA',
        'CALAMA',
        'IQUIQUE',
        'ARICA',
        'LA SERENA',
        'COQUIMBO',
        'RANCAGUA',
        'TALCA',
        'CHILLÁN'
    ],
    
    // Configuración de validaciones
    VALIDATION: {
        RUT_PATTERN: /^\d{7,8}-[\dkK]$/,
        EMAIL_PATTERN: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        PHONE_PATTERN: /^\+?56\d{8,9}$/,
        MAX_DESCRIPTION_LENGTH: 1000,
        MAX_ITEMS: 100,
        MIN_AMOUNT: 1,
        MAX_AMOUNT: 999999999
    },
    
    // Configuración de notificaciones
    NOTIFICATION: {
        DURATION: 5000, // 5 segundos
        TYPES: {
            SUCCESS: 'success',
            ERROR: 'error',
            WARNING: 'warning',
            INFO: 'info'
        }
    },
    
    // Configuración de paginación
    PAGINATION: {
        DEFAULT_LIMIT: 20,
        MAX_LIMIT: 100
    },
    
    // Mensajes del sistema
    MESSAGES: {
        CONNECTION: {
            CONNECTED: 'Conectado',
            DISCONNECTED: 'Desconectado',
            ERROR: 'Error de conexión'
        },
        LOADING: {
            GENERATING_DTE: 'Generando DTE...',
            GENERATING_BHE: 'Generando BHE...',
            GENERATING_PDF: 'Generando PDF...',
            UPLOADING: 'Subiendo archivo...',
            PROCESSING: 'Procesando...'
        },
        SUCCESS: {
            DTE_GENERATED: 'DTE generado exitosamente',
            BHE_GENERATED: 'BHE generada exitosamente',
            PDF_GENERATED: 'PDF generado exitosamente',
            CERTIFICATE_UPLOADED: 'Certificado subido exitosamente',
            FORM_RESET: 'Formulario limpiado'
        },
        ERROR: {
            INVALID_DATA: 'Datos inválidos',
            CONNECTION_ERROR: 'Error de conexión',
            INTERNAL_ERROR: 'Error interno del servidor',
            FILE_REQUIRED: 'Debe seleccionar un archivo',
            INVALID_FILE_TYPE: 'Tipo de archivo no válido',
            DOCUMENT_NOT_FOUND: 'Documento no encontrado',
            CERTIFICATE_NOT_FOUND: 'Certificado no encontrado'
        }
    },
    
    // Configuración de características del sistema
    FEATURES: {
        SII_CERTIFICATION: true,
        DIGITAL_SIGNATURE: true,
        QR_CODES: true,
        PDF_GENERATION: true,
        BHE_SUPPORT: true,
        BULK_OPERATIONS: false // Para futuras implementaciones
    },
    
    // URLs de documentación y ayuda
    HELP_URLS: {
        SII_DOCUMENTATION: 'https://www.sii.cl/factura_electronica/',
        API_DOCUMENTATION: '#',
        CONTACT_EMAIL: 'soporte@donfactura.cl',
        GITHUB_REPO: 'https://github.com/donfactura/api'
    }
};

// Funciones de utilidad globales
const UTILS = {
    // Formatear RUT chileno
    formatRUT(rut) {
        if (!rut) return '';
        const cleaned = rut.replace(/[^\dkK]/g, '');
        if (cleaned.length < 8) return rut;
        
        const body = cleaned.slice(0, -1);
        const dv = cleaned.slice(-1);
        return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
    },
    
    // Validar RUT chileno
    validateRUT(rut) {
        if (!rut || !CONFIG.VALIDATION.RUT_PATTERN.test(rut)) return false;
        
        const [body, dv] = rut.split('-');
        let sum = 0;
        let factor = 2;
        
        for (let i = body.length - 1; i >= 0; i--) {
            sum += parseInt(body[i]) * factor;
            factor = factor === 7 ? 2 : factor + 1;
        }
        
        const remainder = sum % 11;
        const expectedDV = remainder < 2 ? remainder.toString() : (11 - remainder === 10 ? 'K' : (11 - remainder).toString());
        
        return dv.toUpperCase() === expectedDV;
    },
    
    // Formatear moneda chilena
    formatCurrency(amount) {
        if (isNaN(amount) || amount === null || amount === undefined) return '0';
        return new Intl.NumberFormat('es-CL').format(Math.round(amount));
    },
    
    // Formatear fecha para Chile
    formatDate(date) {
        if (!date) return '';
        return new Date(date).toLocaleDateString('es-CL');
    },
    
    // Validar email
    validateEmail(email) {
        return CONFIG.VALIDATION.EMAIL_PATTERN.test(email);
    },
    
    // Limpiar número telefónico
    cleanPhone(phone) {
        return phone ? phone.replace(/[^\d+]/g, '') : '';
    },
    
    // Generar ID único para elementos del DOM
    generateId() {
        return 'id_' + Math.random().toString(36).substr(2, 9);
    },
    
    // Debounce para búsquedas
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Obtener fecha actual en formato ISO
    getCurrentDate() {
        return new Date().toISOString().split('T')[0];
    },
    
    // Calcular total de items
    calculateItemTotal(quantity, unitPrice, discount = 0) {
        const subtotal = (quantity || 0) * (unitPrice || 0);
        const discountAmount = subtotal * (discount || 0) / 100;
        return subtotal - discountAmount;
    },
    
    // Calcular totales de DTE
    calculateDTETotals(items) {
        let subtotal = 0;
        let totalDiscount = 0;
        
        items.forEach(item => {
            const itemSubtotal = (item.cantidad || 0) * (item.precio_unitario || 0);
            const itemDiscount = itemSubtotal * (item.descuento_porcentaje || 0) / 100;
            
            subtotal += itemSubtotal;
            totalDiscount += itemDiscount;
        });
        
        const neto = subtotal - totalDiscount;
        const iva = neto * 0.19; // 19% IVA
        const total = neto + iva;
        
        return {
            subtotal,
            totalDiscount,
            neto,
            iva,
            total
        };
    },
    
    // Calcular retención BHE
    calculateBHERetention(grossAmount, retentionPercentage = 10) {
        const retention = (grossAmount || 0) * (retentionPercentage || 0) / 100;
        const netAmount = (grossAmount || 0) - retention;
        
        return {
            grossAmount: grossAmount || 0,
            retention,
            netAmount,
            retentionPercentage: retentionPercentage || 0
        };
    }
};

// Exportar configuración para uso global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CONFIG, UTILS };
}
