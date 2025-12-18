# Banking Guarantee Letters Management System
## Technical Overview & System Logic Specification
> **STATUS:** Reference Architecture for Phase 6 Implementation

---

## 1. Concept & Vision

The Banking Guarantee Letters Management System is designed to transform raw, heterogeneous banking guarantee data into structured, reliable guarantee records, and to generate official, ready-to-use banking guarantee letters with minimal user intervention.

The system follows a deterministic processing core supported by assisted user decision-making and progressive learning, ensuring accuracy, consistency, and long-term usability without sacrificing user control.

---

## 2. High-Level Objective

The primary objectives of the system are:

- Unify multiple input sources into a single, consistent internal data model.
- Guarantee one record per banking guarantee with no duplication.
- Apply a single, unified processing logic regardless of guarantee origin.
- Allow user intervention only when ambiguity exists.
- Progressively reduce manual intervention through learning.
- Generate legally correct, template-based guarantee letters.

---

## 3. Core Design Principles

1. One Guarantee = One Record = One Letter  
2. Processing logic is always unified  
3. Letter wording varies, not processing  
4. User decisions override automation  
5. Learning assists, never decides  
6. Templates handle all output differences  

---

## 4. Core Domain Concepts

### 4.1 Guarantee Record

A Guarantee Record represents a single banking guarantee operation within the system.  
It is the atomic unit of processing, review, learning, and output.

---

### 4.2 Letter Type

Defines the legal purpose of the generated letter.  
Only the following types are allowed:

- Guarantee Renewal Request
- Guarantee Amount Reduction Request
- Guarantee Information Confirmation Request
- Guarantee Release Request

---

### 4.3 Guarantee Reference Type

Defines why the guarantee was issued, not how it is processed.

- Contract-Based Guarantee
- Purchase-Order-Based Guarantee

This attribute never affects processing logic and is used only to select the correct letter template.

---

## 5. Input Specifications

### 5.1 Supported Input Methods

- Excel Import (batch input)
- Direct Paste (free-text input)
- Manual Form (single guarantee)
- OCR (planned)

### 5.2 Normalization Rule

Regardless of source, all inputs are normalized into the same internal structure before processing.

---

## 6. Processing & Execution Logic

### 6.1 Unified Processing Pipeline

1. Raw data normalization  
2. Numeric and date parsing  
3. Bank name resolution  
4. Supplier name resolution  
5. Guarantee type identification  
6. Reference type identification  
7. Uniqueness validation  
8. Record creation  

---

### 6.2 Ambiguity Handling

When ambiguity is detected, automated processing is paused and the record is routed to the Decision Panel. No assumptions are auto-applied.

---

## 7. User Intervention (Decision Panel)

### When
- Matching uncertainty
- Conflicting data
- Validation failures

### Where
- Decision Panel before record finalization

### How
- Correct mappings
- Adjust values
- Approve or reject suggestions

User intervention is explicit, intentional, and traceable.

---

## 8. Learning Logic (Assisted Intelligence)

### 8.1 Purpose

Learning improves inference accuracy, recognizes recurring patterns, and reduces future user intervention.  
It never replaces user authority.

### 8.2 Learning Sources

- Repeated raw inputs
- User corrections
- Confirmed mappings
- Decision outcomes

### 8.3 Learning Output

- Improved matching confidence
- Better suggestion ranking
- Reduced ambiguity

All learning outputs remain suggestions only.

---

## 9. Output Generation

### 9.1 Template Selection

Final Letter Template =  
Letter Type + Guarantee Reference Type

### 9.2 Output Methods

- Direct printing (single or batch)
- PDF export per record (currently disabled)

Output is pure rendering with no business logic.

---

## 10. End-to-End Flow

Input  
→ Normalize  
→ Process  
→ Decision Panel (if needed)  
→ Learn  
→ Generate Letter  
→ Output  

---

## 11. Final Technical Summary

The system is a deterministic processing engine with user-assisted decision points and learning-driven optimization, producing template-based legal documents.

Any future development must preserve this balance.
