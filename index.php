<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wählerverzeichnis Erstellen - PORTAL²</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <header class="top-header">
        <div class="container">
            <a href="#" class="logo">
                <i class="fas fa-home"></i> PORTAL²
            </a>
            <a href="#" class="external-login">Anmelden am externen System</a>
        </div>
    </header>

    <nav class="main-nav">
        <div class="container">
            <ul>
                <li><a href="#">Startseite</a></li>
                <li><a href="#" class="active">Studienangebot</a></li>
                <li><a href="#">Meine Funktionen</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <div class="breadcrumb">
            Sie sind hier: <a href="#">Startseite</a> &gt; <a href="#">Studienangebot</a> &gt; Wählerverzeichnis Erstellen
        </div>

        <h1>Wählerverzeichnis Erstellen</h1>

        <div class="search-box">
            <form id="voterForm">
                <div class="form-group">
                    <label for="stichtag">Stichtag</label>
                    <input type="date" id="stichtag" name="stichtag" min="2010-01-01" max="2025-08-01" required>
                </div>
                <div class="form-group">
                    <label for="wahl">Wahl</label>
                    <select id="wahl" name="wahl" required>
                        <?php
                        $wahlen = [
                            "gremienwahl" => "Gremienwahl",
                            "studierendenwahl" => "Studierendenwahl",
                            "personalratswahl" => "Personalratswahl"
                        ];
                        foreach ($wahlen as $value => $label) {
                            echo "<option value=\"$value\">$label</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="buttons">
                    <button type="submit" id="createButton">Erstellen</button>
                    <button type="button" class="link-button" id="downloadButton">
                        <i class="fas fa-download"></i> Download <i class="fas fa-file-excel"></i>
                    </button>
                </div>
            </form>
        </div>
    </main>

    
    <div id="voterResults" class="results-table full-width-section">
        <!--<p>Bitte füllen Sie das Formular aus und klicken Sie auf "Erstellen", um das Wählerverzeichnis zu generieren.</p> -->
    </div>

    <footer class="main-footer">
        <div class="footer-layout">
            <div class="footer-section footer-links">
                <nav>
                    <a> </a>
                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/imprint/imprint.faces">IMPRESSUM</a>
                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/imprint/privacy.faces">DATENSCHUTZ</a>
                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/utilities/accessibilityStatement.faces">BARRIEREFREIHEIT</a>

                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/utilities/easyLanguageHelp.faces?" title="Leichte Sprache"><i class="fas fa-book-reader"></i></a>
                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/utilities/signLanguageHelp.faces?" title="Gebärdensprache"><i class="fas fa-sign-language"></i></a>
                    <a href="https://portal2.uni-mannheim.de/portal2/pages/cs/sys/portal/sitemap/sitemap.faces" title="Struktur des Webangebots"><i class="fas fa-sitemap"></i></a>
                    <span>&copy; HISINONE EIN PRODUKT DER HIS EG</span>
                </nav>
            </div>
        </div>
        <div class="footer-section footer-center">
            <span class="his-logo-text">HISinOne</span>
        </div>
        
    </footer>

    <script src="script.js"></script>

    <div id="loadingOverlayDownload" style="display: none;">
        <div class="loading-modal">
            <div class="spinner"></div>
            <p>Download wird vorbereitet...</p>
            <p>Bitte warten.</p>
        </div>
    </div>

    <div id="loadingOverlayErstellen" style="display: none;">
        <div class="loading-modal">
            <div class="spinner"></div>
            <p>Wählerverzeichnis wird erstellt...</p>
            <p>Bitte warten.</p>
        </div>
    </div>

</body>
</html>