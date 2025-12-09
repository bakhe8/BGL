# Project Summary

## 1. Introduction
This project is a **local, offline desktop system** built using:
- **PHP Desktop** (bundled Chromium + PHP runtime)
- **SQLite database**
- **HTML/CSS/JavaScript (no framework)**
- **TailwindCSS (precompiled into one static CSS file)**

Its purpose is to **manage bank guarantee records**, clean inconsistent supplier names, and ensure reliable matching using structured dictionaries.

The system replaces the older browser‑based React/Node version with a **fully contained offline application**, designed for:
- Simplicity  
- Portability  
- Zero server dependencies  
- Full data privacy  

---

## 2. Core Mission
To provide a **stable, deterministic tool** for:
- Importing data from Excel
- Normalizing & matching supplier names
- Reviewing and correcting names
- Maintaining a dictionary of suppliers
- Managing *Alternative Names* and *Overrides*
- Producing clean, consistent outputs

---

## 3. High‑Level Architecture
The system is divided into four major layers:

### **1) Input Layer**
Handles:
- Excel imports  
- Manual data entry  
- Paste input  

### **2) Processing Layer**
Contains the main logic:
- Normalization rules  
- Matching engine  
- Alternative names resolution  
- Override logic  
- Validation  
- Record analysis  

### **3) Review Layer**
The interactive UI where the user performs:
- Supplier confirmation  
- Conflict resolution  
- Dictionary editing  
- Exporting processed results  

### **4) Dictionary Layer**
Persistent storage of:
- Suppliers  
- Alternative names  
- Overrides  
- Audit logs  

---

## 4. Why This Architecture?
The system is designed to be:
- **Portable** — runs on any Windows PC without installation.
- **Fast** — everything is local (SQLite + PHP).
- **Transparent** — all data stored in readable files.
- **Maintainable** — clear separation of dictionary logic, matching logic, and UI logic.

---

## 5. Target Users
- Guarantee processing teams  
- Data validation/cleaning personnel  
- Users who require **high accuracy, repeatability, and control**  

---

## 6. Out‑of‑Scope
The system does *not* handle:
- Online sync  
- Server API  
- User accounts or multi‑user collaboration  
- OCR/PDF recognition  
(These can be added later.)

---

## 7. Project Vision
A **long‑term, offline‑first data standardization system** that ensures:
- Clean data  
- Consistent naming  
- High accuracy  
- Minimal user effort  

