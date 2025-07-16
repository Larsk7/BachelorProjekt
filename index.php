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
                    <input type="date" id="stichtag" name="stichtag" required>
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
                    <button type="button" class="link-button"><i class="fas fa-eye"></i> Preview</button>
                    <button type="button" class="link-button"><i class="fas fa-download"></i> Download</button>
                </div>
            </form>
        </div>

        <div id="voterResults" class="results-table">
            <p>Bitte füllen Sie das Formular aus und klicken Sie auf "Erstellen", um das Wählerverzeichnis zu generieren.</p>
        </div>

    </main>

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


            </div>
        </div>
        <div class="footer-section footer-center">

            <span class="his-logo-text">HISinOne</span>

        </div>

        <div class="footer-section footer-meta">
            <div class="language-selector">
                <span>Standardsprache</span>
                <a href="#" class="language-button"><i class="fas fa-globe"></i> Deutsch</a>
            </div>
            <span class="tech-info">GENERIERT VOM KNOTEN V2_5 IM CLUSTER PROD.</span>
        </div>

    </footer>

    <script src="script.js"></script>

</body>

</html>