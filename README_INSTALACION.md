# 🚀 INSTALACIÓN RÁPIDA - SISTEMA BHE

## Boletas de Honorarios Electrónicas (DTE Tipo 41) - Chile

---

## 📋 OPCIONES DE INSTALACIÓN

### 🏃‍♂️ **INSTALACIÓN RÁPIDA**

#### **🐧 Linux/Apache - Automática**
```bash
# Descargar e instalar automáticamente
curl -O https://raw.githubusercontent.com/tu-repo/install_bhe.sh
chmod +x install_bhe.sh
sudo ./install_bhe.sh
```

#### **🏠 XAMPP - Windows**
```batch
# Descargar e instalar automáticamente
curl -O https://raw.githubusercontent.com/tu-repo/install_xampp.bat
install_xampp.bat
```

### 📖 **INSTALACIÓN MANUAL**
Ver documentación completa: **[MANUAL_INSTALACION_BHE.md](MANUAL_INSTALACION_BHE.md)**

---

## ⚡ INICIO RÁPIDO

### **1. Verificar Instalación**
```bash
php verificar_instalacion.php
```

### **2. Configurar Sistema BHE**
```bash
php setup_bhe.php
php create_folios_bhe.php
php create_certificados_bhe.php
```

### **3. Verificar Funcionamiento**
```bash
# Via web
http://localhost/donfactura/public/bhe-features

# Via script
php test_bhe_system.php
```

---

## 🎯 FUNCIONALIDADES DISPONIBLES

- ✅ **Generación BHE DTE Tipo 41**
- ✅ **Firma electrónica obligatoria**
- ✅ **Retención automática 10%**
- ✅ **PDF formatos carta y térmico**
- ✅ **API REST completa**
- ✅ **Gestión profesionales**

---

## 📞 SOPORTE

### **🔍 Verificación**
```bash
php verificar_instalacion.php
```

### **📋 Documentación**
- `MANUAL_INSTALACION_BHE.md` - Manual completo
- `FUNCIONALIDADES_BHE_IMPLEMENTADAS.md` - Funcionalidades
- `RESUMEN_SISTEMA_BHE_COMPLETO.md` - Resumen ejecutivo

### **🧪 Pruebas**
```bash
php test_bhe_system.php        # Test completo
php test_generar_bhe.php       # Test generación
php test_pdf_bhe.php          # Test PDF
```

---

## 🎉 ¡LISTO PARA USAR!

Una vez completada la instalación, el sistema estará operativo para generar **Boletas de Honorarios Electrónicas** válidas según la normativa del **SII Chile**.

**URL Principal:** `http://localhost/donfactura/public/bhe-features`
