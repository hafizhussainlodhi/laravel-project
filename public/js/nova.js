// Nova uses localStorage for the theme, so we set the theme to 'light'.
localStorage.setItem("novaTheme", "light");
// Also ensure the 'dark' class is removed from the document element if present.
document.documentElement.classList.remove("dark");