"use client";

import { useState } from "react";
import {
  FormControl,
  InputLabel,
  MenuItem,
  Select,
  SelectChangeEvent,
  Stack,
  Typography,
  Dialog,
  DialogTitle,
  DialogContent,
  TextField,
  List,
  ListItemButton,
  ListItemText,
  InputAdornment,
  Box,
} from "@mui/material";
import SearchIcon from "@mui/icons-material/Search";

export type Currency = string;

interface CurrencySelectorProps {
  value: Currency;
  onChange: (currency: Currency) => void;
  label?: string;
  size?: "small" | "medium";
}

export const currencies: { code: string; symbol: string; name: string }[] = [
  { code: "INR", symbol: "₹", name: "Indian Rupee" },
  { code: "USD", symbol: "$", name: "US Dollar" },
  { code: "EUR", symbol: "€", name: "Euro" },
  { code: "GBP", symbol: "£", name: "British Pound" },
  { code: "JPY", symbol: "¥", name: "Japanese Yen" },
  { code: "AUD", symbol: "A$", name: "Australian Dollar" },
  { code: "SGD", symbol: "S$", name: "Singapore Dollar" },
  { code: "CHF", symbol: "CHF", name: "Swiss Franc" },
  { code: "CAD", symbol: "C$", name: "Canadian Dollar" },
  { code: "CNY", symbol: "¥", name: "Chinese Yuan" },
  { code: "AED", symbol: "د.إ", name: "UAE Dirham" },
  { code: "KRW", symbol: "₩", name: "South Korean Won" },
  { code: "SEK", symbol: "kr", name: "Swedish Krona" },
  { code: "NZD", symbol: "NZ$", name: "New Zealand Dollar" },
  { code: "MXN", symbol: "$", name: "Mexican Peso" },
  { code: "HKD", symbol: "HK$", name: "Hong Kong Dollar" },
  { code: "NOK", symbol: "kr", name: "Norwegian Krone" },
  { code: "TRY", symbol: "₺", name: "Turkish Lira" },
  { code: "RUB", symbol: "₽", name: "Russian Ruble" },
  { code: "ZAR", symbol: "R", name: "South African Rand" },
  { code: "BRL", symbol: "R$", name: "Brazilian Real" },
  { code: "SAR", symbol: "﷼", name: "Saudi Riyal" },
];

export function getCurrencySymbol(currency: Currency): string {
  return currencies.find((c) => c.code === currency)?.symbol ?? currency;
}

export default function CurrencySelector({
  value,
  onChange,
  label = "Currency",
  size = "small",
}: CurrencySelectorProps) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [search, setSearch] = useState("");

  const topCurrencies = currencies.slice(0, 5);
  const isCustomValue = value && !topCurrencies.find((c) => c.code === value);
  const customCurrency = isCustomValue ? currencies.find((c) => c.code === value) : null;

  const handleChange = (event: SelectChangeEvent) => {
    if (event.target.value === "SEE_MORE") {
      setDialogOpen(true);
      setSearch("");
    } else {
      onChange(event.target.value);
    }
  };

  const handleSelectFromDialog = (code: string) => {
    onChange(code);
    setDialogOpen(false);
  };

  const filteredCurrencies = currencies.filter(
    (c) =>
      c.code.toLowerCase().includes(search.toLowerCase()) ||
      c.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <>
      <FormControl size={size} sx={{ minWidth: 120 }}>
        <InputLabel>{label}</InputLabel>
        <Select value={value || "INR"} label={label} onChange={handleChange}>
          {topCurrencies.map((currency) => (
            <MenuItem key={currency.code} value={currency.code}>
              <Stack direction="row" spacing={1} alignItems="center">
                <Typography fontWeight={600}>{currency.symbol}</Typography>
                <Typography>{currency.code}</Typography>
              </Stack>
            </MenuItem>
          ))}
          {isCustomValue && (
            <MenuItem value={value}>
              <Stack direction="row" spacing={1} alignItems="center">
                <Typography fontWeight={600}>{customCurrency?.symbol || "$"}</Typography>
                <Typography>{value}</Typography>
              </Stack>
            </MenuItem>
          )}
          <MenuItem value="SEE_MORE">
            <Typography color="primary" fontWeight={500}>
              See More...
            </Typography>
          </MenuItem>
        </Select>
      </FormControl>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>Select Currency</DialogTitle>
        <DialogContent dividers sx={{ p: 0 }}>
          <Box sx={{ p: 2, pb: 1 }}>
            <TextField
              autoFocus
              fullWidth
              size="small"
              placeholder="Search currencies..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              slotProps={{
                input: {
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon />
                    </InputAdornment>
                  ),
                },
              }}
            />
          </Box>
          <List sx={{ pt: 0, maxHeight: 300, overflow: "auto" }}>
            {filteredCurrencies.map((currency) => (
              <ListItemButton
                key={currency.code}
                onClick={() => handleSelectFromDialog(currency.code)}
                selected={value === currency.code}
              >
                <Stack direction="row" spacing={2} alignItems="center">
                  <Typography fontWeight={600} sx={{ minWidth: 30 }}>
                    {currency.symbol}
                  </Typography>
                  <ListItemText primary={currency.code} secondary={currency.name} />
                </Stack>
              </ListItemButton>
            ))}
            {filteredCurrencies.length === 0 && (
              <Box sx={{ p: 2, textAlign: "center" }}>
                <Typography color="text.secondary">No currencies found.</Typography>
              </Box>
            )}
          </List>
        </DialogContent>
      </Dialog>
    </>
  );
}
