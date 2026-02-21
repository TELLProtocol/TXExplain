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
    const url = `/arbitrum-project/get-details.php?tx=${txHash}`;

    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Server error: ${response.status}`);
    }

    const data = await response.json();
    return data;
}

/**
 * Classify transaction type
 */
function detectTransactionAction(data) {

    if (!data) return "unknown";

    if (data.swap_analysis) return "swap";

    if (data.is_swap === true) return "swap";

    const transfers = data.token_transfers || [];
    const uniqueTokens = new Set(
        transfers.map(t => t.token_contract).filter(Boolean)
    );

    if (uniqueTokens.size >= 2) return "swap";

    if (transfers.length === 1) return "transfer";

    const tx = data.tx_raw?.result;

    if (tx && tx.value && tx.value !== "0x0") {
        return "eth_transfer";
    }

    return "contract_interaction";
}

/**
 * Render dynamic results into UI
 */
function renderResult(txHash, classification, rawData) {

    document.getElementById("resultPlaceholder").style.display = "none";
    const resultContent = document.getElementById("resultContent");
    resultContent.style.display = "block";

    // Update transaction hash display
    const hashDisplay = resultContent.querySelector(".transaction-hash-display small");
    if (hashDisplay) {
        hashDisplay.textContent = txHash;
    }

    // Update summary title
    const title = resultContent.querySelector(".transaction-summary h3");
    if (title) {
        title.textContent = `Transaction Type: ${classification.toUpperCase()}`;
    }

    // Dynamically show token transfers
    const summaryParagraph = resultContent.querySelector(".transaction-summary p");

    if (rawData?.token_transfers?.length > 0) {
        const transfersText = rawData.token_transfers
            .map(t => `${t.amount || ""} ${t.token_symbol || "TOKEN"} from ${t.from} → ${t.to}`)
            .join("<br>");

        summaryParagraph.innerHTML = transfersText;
    } else {
        summaryParagraph.textContent = "No token transfers detected.";
    }
}

/**
 * Show error inside result section
 */
function showError(message) {

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
 * Show loading state
 */
function showLoading() {

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
 * Initialize form listener
 */
document.getElementById("transactionForm")
    .addEventListener("submit", async function (e) {

        e.preventDefault();

        const txHashInput = document.getElementById("transactionHash");
        const txHash = txHashInput.value.trim();

        if (!isValidTxHash(txHash)) {
            alert("Invalid transaction hash format.");
            return;
        }

        try {

            showLoading();

            const data = await fetchTransactionData(txHash);

            const classification = detectTransactionAction(data);

            renderResult(txHash, classification, data);

        } catch (err) {
            console.error(err);
            showError(err.message);
        }
    });

/**
 * Add spinner animation CSS dynamically
 */
const style = document.createElement("style");
style.innerHTML = `
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
`;
document.head.appendChild(style);
