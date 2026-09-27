import React, { useMemo, useState, useEffect } from "react";
import {
  TextField,
  MenuItem,
  Box,
  InputAdornment,
} from "@mui/material";
import {
  getCountries,
  getCountryCallingCode,
  CountryCode,
  parsePhoneNumberFromString,
  AsYouType,
  validatePhoneNumberLength,
  isValidPhoneNumber,
} from "libphonenumber-js";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";

interface PhoneInputProProps {
  value: string;
  onChange: (value: string) => void;
  defaultCountry?: CountryCode;
  error?: boolean;
  helperText?: React.ReactNode;
  label?: string;
  required?: boolean;
  disabled?: boolean;
}

export default function PhoneInputPro({
  value,
  onChange,
  defaultCountry = "IN",
  error,
  helperText,
  label = "Mobile Number",
  required = false,
  disabled = false,
}: PhoneInputProProps) {
  const countries = useMemo(() => getCountries(), []);
  const [localCountry, setLocalCountry] = useState<CountryCode>(defaultCountry);

  const parsed = useMemo(() => parsePhoneNumberFromString(value || ""), [value]);
  
  useEffect(() => {
    if (parsed && parsed.country && parsed.country !== localCountry) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- works correctly; refactor deferred, see D33
      setLocalCountry(parsed.country);
    }
  }, [parsed?.country]); // eslint-disable-line react-hooks/exhaustive-deps

  const currentCountry = localCountry;

  const displayValue = useMemo(() => {
    const callingCode = getCountryCallingCode(currentCountry);
    let rawNational = value || "";
    // Remove the calling code if present to get just the national part
    if (rawNational.startsWith(`+${callingCode}`)) {
      rawNational = rawNational.slice(callingCode.length + 1).trim();
    }
    const cleanNational = rawNational.replace(/\D/g, "");
    const f = new AsYouType(currentCountry);
    return f.input(cleanNational);
  }, [value, currentCountry]);

  const handleCountryChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newCountry = e.target.value as CountryCode;
    setLocalCountry(newCountry);
    const callingCode = getCountryCallingCode(newCountry);

    // Attempt to preserve the national part when switching countries
    const callingCodePrev = getCountryCallingCode(currentCountry);
    let rawNational = value || "";
    if (rawNational.startsWith(`+${callingCodePrev}`)) {
      rawNational = rawNational.slice(callingCodePrev.length + 1).trim();
    }
    const cleanNational = rawNational.replace(/\D/g, "");

    onChange(cleanNational ? `+${callingCode} ${cleanNational}` : `+${callingCode} `);
  };

  const handleNumberChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const rawVal = e.target.value;
    const cleanNational = rawVal.replace(/\D/g, "");
    
    // E.164 limits total length to 15 digits
    if (cleanNational.length > 15) return;

    const callingCode = getCountryCallingCode(currentCountry);
    onChange(`+${callingCode} ${cleanNational}`);
  };

  const hasDigits = /\d/.test(displayValue);

  const lengthValidation = useMemo(() => {
    if (!hasDigits) return null;
    try {
      // The validator works best with the full E.164 number
      const callingCode = getCountryCallingCode(currentCountry);
      let rawNational = value || "";
      if (rawNational.startsWith(`+${callingCode}`)) {
        rawNational = rawNational.slice(callingCode.length + 1).trim();
      }
      const cleanNational = rawNational.replace(/\D/g, "");
      const fullNumberForValidation = `+${callingCode}${cleanNational}`;
      return validatePhoneNumberLength(fullNumberForValidation, currentCountry);
    } catch {
      return 'INVALID_LENGTH';
    }
  }, [value, currentCountry, hasDigits]);

  const isValid = useMemo(() => {
    if (!hasDigits) return true;
    return isValidPhoneNumber(value, currentCountry);
  }, [value, currentCountry, hasDigits]);

  let liveErrorMsg = null;
  if (hasDigits && !isValid) {
    if (lengthValidation === 'TOO_SHORT') {
      liveErrorMsg = "Number is too short";
    } else if (lengthValidation === 'TOO_LONG') {
      liveErrorMsg = "Number is too long";
    } else {
      liveErrorMsg = "Invalid number length or format";
    }
  }

  const isError = error || Boolean(liveErrorMsg);
  const displayHelperText = error ? helperText : (liveErrorMsg || helperText);

  const regionNames = useMemo(() => {
    try {
      return new Intl.DisplayNames(["en"], { type: "region" });
    } catch {
      return null;
    }
  }, []);

  return (
    <Box sx={{ display: "flex", gap: 1, width: "100%", flexWrap: "wrap" }}>
      <TextField
        select
        value={currentCountry}
        onChange={handleCountryChange}
        disabled={disabled}
        sx={{ width: 110, flexShrink: 0 }}
        SelectProps={{
          MenuProps: {
            sx: { maxHeight: 400 },
          },
          renderValue: (val: unknown) => {
            const code = val as CountryCode;
            return `${code} (+${getCountryCallingCode(code)})`;
          }
        }}
      >
        {countries.map((c) => (
          <MenuItem key={c} value={c}>
            {regionNames ? regionNames.of(c) : c} (+{getCountryCallingCode(c)})
          </MenuItem>
        ))}
      </TextField>
      <TextField
        value={displayValue}
        onChange={handleNumberChange}
        label={label}
        required={required}
        error={isError}
        helperText={displayHelperText}
        disabled={disabled}
        placeholder="Enter number"
        sx={{ flex: "1 1 120px", minWidth: 0 }}
        InputProps={{
          startAdornment: (
            <InputAdornment position="start">
              +{getCountryCallingCode(currentCountry)}
            </InputAdornment>
          ),
          endAdornment: hasDigits && isValid ? (
            <InputAdornment position="end">
              <CheckCircleIcon color="success" />
            </InputAdornment>
          ) : null,
        }}
      />
    </Box>
  );
}
