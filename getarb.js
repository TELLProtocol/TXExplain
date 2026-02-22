/**
 * Validate Ethereum-style transaction hash
 */
function isValidTxHash(hash) {
    return /^0x([A-Fa-f0-9]{64})$/.test(hash);
}

/**
 * Fetch transaction data from backend
 */
async function fetchTransactionData(txHash) {
    const url = `/arbitrum-project/gh.php?tx=${txHash}`;
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
    }

    return await response.json();
}

/**
 * Classify transaction
 */
function classifyTransaction(data) {
    if (!data) return "unknown";

    if (data.is_swap || data.is_swap_advanced) return "swap";

    if (data.token_transfers?.length === 1) return "token_transfer";

    const tx = data.tx_raw?.result;
    if (tx?.value && tx.value !== "0x0") return "eth_transfer";

    return "contract_interaction";
}

/**
 * Shorten address helper
 */
function shortAddr(addr) {
    if (!addr) return "";
    return addr.slice(0, 6) + "..." + addr.slice(-4);
}

/**
 * Detect user-level swap (collapse router hops)
 */
function buildSwapSummary(data) {

    if (!data.is_swap || !data.token_transfers?.length) return null;

    const user = data.tx_raw?.result?.from?.toLowerCase();
    if (!user) return null;

    let sent = null;
    let received = null;

    data.token_transfers.forEach(t => {
        if (t.from.toLowerCase() === user) sent = t;
        if (t.to.toLowerCase() === user) received = t;
    });

    if (!sent || !received) return null;

    return {
        tokenIn: sent,
        tokenOut: received
    };
}

/**
 * Replace Example Section With Dynamic Swap Summary
 */
function renderSwapSummary(data) {

    const exampleSection = document.querySelector(".example-section");
    if (!exampleSection) return;

    const swap = buildSwapSummary(data);

    if (!swap) {
        exampleSection.style.display = "none";
        return;
    }

    exampleSection.style.display = "block";

    exampleSection.innerHTML = `
        <h2 class="section-title">
            <i class="ph ph-arrows-left-right"></i>
            Swap Summary
        </h2>

        <div class="example-hash" style="font-size: 1.2rem;">
            Swapped 
            <strong>${swap.tokenIn.value_human}</strong> 
            ${shortAddr(swap.tokenIn.token_contract)}
            →
            <strong>${swap.tokenOut.value_human}</strong> 
            ${shortAddr(swap.tokenOut.token_contract)}
        </div>

        <ul class="example-list">
            <li>
                <i class="ph ph-wallet"></i>
                From: ${shortAddr(data.tx_raw.result.from)}
            </li>
            <li>
                <i class="ph ph-gas-pump"></i>
                Gas Used: ${data.gas_cost?.eth || "N/A"} ETH
            </li>
            <li>
                <i class="ph ph-check-circle"></i>
                Status: ${data.receipt_raw?.result?.status === "0x1" ? "Completed" : "Failed"}
            </li>
        </ul>
    `;
}

/**
 * Render full result section
 */
function renderResult(txHash, data) {

    renderSwapSummary(data);

    const classification = classifyTransaction(data);

    document.getElementById("resultPlaceholder").style.display = "none";
    const resultContent = document.getElementById("resultContent");
    resultContent.style.display = "block";

    // Header
    const hashDisplay = resultContent.querySelector(".transaction-hash-display small");
    if (hashDisplay) hashDisplay.textContent = txHash;

    const title = resultContent.querySelector(".result-title");
    if (title) title.textContent = `Transaction Analysis (${classification.toUpperCase()})`;

    // Status
    const statusText = data.receipt_raw?.result?.status === "0x1"
        ? "Completed"
        : "Failed";

    const summaryItems = document.querySelectorAll(".summary-item");

    summaryItems.forEach(item => {
        const label = item.querySelector("h4")?.textContent;

        if (label === "Status") {
            item.querySelector("p").textContent = statusText;
        }

        if (label === "Network Fees" && data.gas_cost?.eth) {
            item.querySelector("p").textContent = `${data.gas_cost.eth} ETH`;
        }

        if (label === "Risk Level") {
            const badge = item.querySelector(".risk-badge");

            if (classification === "swap") {
                badge.textContent = "Swap Detected";
                badge.className = "risk-badge risk-medium";
            } else {
                badge.textContent = "Standard";
                badge.className = "risk-badge risk-low";
            }
        }
    });

    // Clear and rebuild token transfer cards
    const detailsGrid = document.querySelector(".details-grid");
    if (!detailsGrid) return;

    detailsGrid.innerHTML = "";

    if (data.token_transfers?.length > 0) {

        data.token_transfers.forEach(t => {

            const card = document.createElement("div");
            card.className = "detail-card";

            card.innerHTML = `
                <h4>Token Transfer</h4>
                <p>
                    <strong>${t.value_human}</strong><br>
                    ${shortAddr(t.from)} → ${shortAddr(t.to)}
                </p>
                <small>${t.token_contract}</small>
            `;

            detailsGrid.appendChild(card);
        });

    } else {

        detailsGrid.innerHTML = `
            <div class="detail-card">
                <h4>No Token Transfers</h4>
                <p>This transaction did not emit ERC20 transfer events.</p>
            </div>
        `;
    }
}

/**
 * Show error
 */
function showError(message) {

    const exampleSection = document.querySelector(".example-section");
    if (exampleSection) exampleSection.style.display = "block";

    const placeholder = document.getElementById("resultPlaceholder");
    placeholder.style.display = "flex";

    placeholder.innerHTML = `
        <i class="ph ph-warning placeholder-icon"></i>
        <h3>Error</h3>
        <p>${message}</p>
    `;

    document.getElementById("resultContent").style.display = "none";
}

/**
 * Show loading
 */
function showLoading() {

    const exampleSection = document.querySelector(".example-section");
    if (exampleSection) exampleSection.style.display = "none";

    const placeholder = document.getElementById("resultPlaceholder");
    placeholder.style.display = "flex";

    placeholder.innerHTML = `
        <i class="ph ph-spinner-gap placeholder-icon"
           style="animation: spin 1s linear infinite;"></i>
        <h3>Analyzing Transaction...</h3>
    `;

    document.getElementById("resultContent").style.display = "none";
}

/**
 * Form submission
 */
document.getElementById("transactionForm")
    .addEventListener("submit", async function (e) {

        e.preventDefault();

        const txHash = document.getElementById("transactionHash").value.trim();

        if (!isValidTxHash(txHash)) {
            alert("Invalid transaction hash format.");
            return;
        }

        try {
            showLoading();
            const data = await fetchTransactionData(txHash);
            renderResult(txHash, data);
        } catch (err) {
            console.error(err);
            showError(err.message);
        }
    });

/**
 * Spinner animation
 */
const style = document.createElement("style");
style.innerHTML = `
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}`;
document.head.appendChild(style);
