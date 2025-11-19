<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Eleanor - Intro V3 (Split Reveal)</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tenor+Sans&family=Cormorant+Garamond:wght@300;400;500;600;700&family=Cormorant:wght@300;400;500&display=swap" rel="stylesheet">
    
    <style>
        /* ======= THE ELEANOR COLOR PALETTE ======= */
        :root {
            --eleanor-cream: #F5F3EF;
            --eleanor-charcoal: #2C2C2C;
            --eleanor-charcoal-light: #4A4A4A;
            --eleanor-pink-accent: #E8C5CE;
            --eleanor-pink: #FFE8EC;
            /* Glassmorphism - Creamier with subtle color */
            --glass-bg-subtle: rgba(245, 243, 239, 0.25);
            --glass-border: rgba(255, 255, 255, 0.15);
            --glass-shadow: 20px 20px 22px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tenor Sans', sans-serif;
            background: var(--eleanor-cream);
            color: var(--eleanor-charcoal);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        .intro-container {
            text-align: center;
            padding: 40px;
            max-width: 100%;
        }
        
        /* Glassmorphism Panel - Subtle Like Example */
        .glass-panel {
            background: transparent;
            backdrop-filter: blur(0px);
            -webkit-backdrop-filter: blur(0px);
            border: none;
            border-radius: 8px;
            padding: 80px 60px;
            box-shadow: none;
            position: relative;
            overflow: visible;
            max-width: 900px;
            margin: 40px;
            text-align: center;
            animation: panelFadeIn 3.5s ease-out 0.5s forwards;
        }
        
        /* Glass Panel - Bold Pink/Green Burst */
        .glass-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(255, 232, 236, 0.7) 0%, transparent 70%),
                        radial-gradient(circle at 70% 50%, rgba(232, 240, 230, 0.7) 0%, transparent 70%);
            border-radius: 8px;
            opacity: 0;
            z-index: -1;
            animation: colorBurstFadeIn 3.5s ease-out 0.5s forwards;
        }
        
        @keyframes colorBurstFadeIn {
            0% {
                opacity: 0;
            }
            100% {
                opacity: 1;
            }
        }
        
        /* Fixed Background Image */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: url('img/background-hero.jpeg') center center / cover no-repeat fixed;
            z-index: -1;
            opacity: 1;
        }
        
        
        h1 {
            font-family: 'Tenor Sans', sans-serif;
            font-size: 4rem;
            font-weight: 400;
            letter-spacing: 0.15em;
            color: #000000;
            text-transform: uppercase;
            text-shadow: 0 2px 8px rgba(255, 255, 255, 0.3);
            white-space: nowrap;
            display: flex;
            gap: 0.5em;
            justify-content: center;
            align-items: center;
        }
        
        .word-left {
            opacity: 0;
            animation: slideFromLeft 3.5s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
        }
        
        .word-right {
            opacity: 0;
            animation: slideFromRight 3.5s cubic-bezier(0.16, 1, 0.3, 1) 0.5s forwards;
        }
        
        p {
            font-family: 'Tenor Sans', sans-serif;
            font-size: 1.2rem;
            color: #000000;
            margin-top: 20px;
            letter-spacing: 0.08em;
            opacity: 0;
            animation: fadeInUp 2s ease-out 2s forwards;
        }
        
        /* Split Reveal - From Left */
        @keyframes slideFromLeft {
            0% {
                opacity: 0;
                transform: translateX(-100vw);
            }
            60% {
                opacity: 1;
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Split Reveal - From Right */
        @keyframes slideFromRight {
            0% {
                opacity: 0;
                transform: translateX(100vw);
            }
            60% {
                opacity: 1;
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Fade In Up for Address */
        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Glass Panel Fade In - Appears at end of animation */
        @keyframes panelFadeIn {
            0% {
                background: transparent;
                backdrop-filter: blur(0px);
                -webkit-backdrop-filter: blur(0px);
                border: none;
                box-shadow: none;
                border-radius: 8px;
            }
            100% {
                background: var(--glass-bg-subtle);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: none;
                box-shadow: var(--glass-shadow);
                border-radius: 8px;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .glass-panel {
                padding: 60px 40px;
                margin: 20px;
            }
            
            h1 {
                font-size: 2.5rem;
                gap: 0.3em;
            }
            
            p {
                font-size: 1rem;
            }
            
            /* Adjust animation for mobile - shorter distance */
            @keyframes slideFromLeft {
                0% {
                    opacity: 0;
                    transform: translateX(-50vw);
                }
                60% {
                    opacity: 1;
                }
                100% {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideFromRight {
                0% {
                    opacity: 0;
                    transform: translateX(50vw);
                }
                60% {
                    opacity: 1;
                }
                100% {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        }
    </style>
</head>
<body>
    <div class="glass-panel">
        <h1>
            <span class="word-left">THE</span>
            <span class="word-right">ELEANOR</span>
        </h1>
        <p>962 & 972 Bushwick Avenue, Brooklyn, NY 11221</p>
        
        <!-- Add your custom intro animation code here -->
    </div>
    
    <script>
        console.log('intro3.php loaded - Split Reveal Animation');
    </script>
</body>
</html>

