# TXExplain (Arbitrum)

## TXExplain — Safety & Intelligence Layer for Arbitrum DeFi

**TXExplain** is a **read-only transaction intelligence engine** built specifically for **Arbitrum**.

It converts raw on-chain transaction data into **clear, human-readable explanations** and highlights potential risks before users make costly mistakes.

Arbitrum is one of the most active DeFi ecosystems. **Complex router-based swaps, approvals, batching, and L2 mechanics** make transactions difficult to interpret for users. **TXExplain** is purpose-built for Arbitrum to improve safety and usability within its ecosystem.

---

## 🌐 Live Demo

Try it here:
👉 https://txexplain.iceily.com

Paste any Arbitrum transaction hash and instantly see:
- What type of transaction it was
- Which contracts and tokens were involved
- Whether there are safety concerns (e.g. unlimited approvals)
- A plain-English explanation of the action

---

## 🧠 The Problem

Blockchain explorers show **what happened**, but not **what it means**.

On Arbitrum, this problem is amplified because:
- Router-based swaps obscure true token flows
- ERC20 approvals can silently grant unlimited access
- Multi-step DeFi interactions are difficult to interpret
- L2 execution and batching add complexity

Most users don’t understand:
- What they approved
- What contracts actually did
- Whether a transaction is risky or safe

**TXExplain** bridges that gap by translating low-level transaction data into explanations that humans can understand.

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

---

## 🧪 How It Works

User pastes a transaction hash

↓

Backend fetches:
- Transaction details

- Transaction receipt

↓

Decoder analyzes:
- Function selectors
- Logs (Transfer, Approval events)
- Known contract addresses
  
↓

Explanation engine generates:
- Transaction type
- Human-readable explanation
- Risk flags

↓

Frontend displays results

---

## ⚠️ Risk Detection Logic

The tool currently detects:
- Unlimited ERC20 approvals
- Unknown or unverified contracts
- High-level contract interactions

All explanations are deterministic and rule-based for reliability.

---

## 🛣️ Roadmap (Post-Hackathon)

- 🔐 Wallet connection (read-only)
- ❌ Approval revocation
- 🤖 AI-enhanced explanations
- 🌉 Cross-chain support
- 📈 Transaction simulation

---

## 🏆 Hackathon Context

Built for the Arbitrum Open House NYC Online Buildathon
Focus: UX, safety, and developer-friendly tooling for the Arbitrum ecosystem.

---

## ⚖️ Disclaimer

This tool is for educational and informational purposes only.
It does not provide financial advice or guarantee transaction safety.

Always verify transactions and contracts independently.

---

## 📜 License

MIT License

---

## 🙌 Author

Built solo by Jasper Saxifrage
