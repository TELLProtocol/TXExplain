# TXExplain (Arbitrum)

TXExplain is a **read-only Arbitrum tool** that converts raw blockchain transactions into **clear, human-readable explanations**, helping users understand what actually happened on-chain and identify potential risks.

Paste any Arbitrum transaction hash and instantly see:
- What type of transaction it was
- Which contracts and tokens were involved
- Whether there are safety concerns (e.g. unlimited approvals)
- A plain-English explanation of the action

---

## 🚀 Why This Project Exists

Blockchain explorers show **what happened**, but not **what it means**.

Most users don’t understand:
- What they approved
- What contracts actually did
- Whether a transaction is risky or safe

This tool bridges that gap by translating low-level transaction data into explanations that humans can understand.

---

## ✨ Features (MVP)

- 🔍 **Transaction decoding** (by hash)
- 🧠 **Plain-English explanations**
- ⚠️ **Risk detection**
  - Unlimited token approvals
  - Unknown / unverified contracts
- ✅ **Verified protocol detection**
- 📊 Gas usage summary
- 🔒 **Read-only** (no wallet connection, no signing)

---

## 🧱 Tech Stack

- **Backend:** PHP  
- **Frontend:** JavaScript, HTML, CSS, Phosphor Icons, HTMX  
- **Blockchain:** Arbitrum One  
- **RPC Provider:** PublicNode (or any Arbitrum-compatible RPC)

---

## 🏗️ Architecture Overview

Browser
↓ (tx hash)
PHP API (Cloudways)
↓
Arbitrum RPC
↓
Decoder + Explanation Engine
↓
JSON response
↓
Frontend UI

---

## 📁 Project Structure

/public

├── index.html

├── app.js

└── styles.css

/api

├── explain.php

├── rpc.php

├── decoder.php

├── explainers.php

└── contracts.php

/config

└── config.php

