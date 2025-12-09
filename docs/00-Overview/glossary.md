# Glossary of Terms

A quick reference for terminology used across the system.

---

## **Supplier**
An entity (company/person) related to a bank guarantee record.

## **Official Name**
The canonical, authoritative name assigned to a supplier.

## **Alternative Names**
All different raw names that refer to the same supplier.
They come from:
- Excel imports
- Manual entry
- Paste input

## **Override**
A forced name that should replace the matched name during processing.

## **Normalized Name**
A cleaned version of the raw name:
- Trimmed  
- Whitespace collapsed  
- Unicode normalized  
- Arabic letters unified  

Used internally for matching.

## **Matching Engine**
The logic that determines which supplier a record belongs to.

## **Review Panel**
The UI where the user inspects and approves/rejects matches.

## **Record**
A single processed row from Excel or manual input.

## **Dictionary**
The stored database of:
- Suppliers  
- Alternative names  
- Overrides  

## **Audit Log**
A history of user decisions and system actions.

