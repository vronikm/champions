<?php
/*
 * AlphaPDF - Extensión de FPDF con soporte de transparencia (canal alpha)
 * Basado en el script oficial de transparencia de FPDF.org
 * Compatible con FPDF 1.86
 */

require_once dirname(__FILE__) . '/fpdf.php';

class AlphaPDF extends FPDF
{
    protected $extgstates = [];

    /**
     * Establece el nivel de transparencia (alpha) para dibujo y texto.
     *
     * @param float  $alpha Valor entre 0 (totalmente transparente) y 1 (opaco)
     * @param string $bm    Blend mode: Normal, Multiply, Screen, Overlay, Darken,
     *                      Lighten, ColorDodge, ColorBurn, HardLight, SoftLight,
     *                      Difference, Exclusion, Hue, Saturation, Color, Luminosity
     */
    public function SetAlpha($alpha, $bm = 'Normal')
    {
        // Buscar si ya existe el mismo estado
        $gs = $this->AddExtGState([
            'ca' => $alpha,
            'CA' => $alpha,
            'BM' => '/' . $bm
        ]);
        $this->SetExtGState($gs);
    }

    protected function AddExtGState($parms)
    {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    protected function SetExtGState($gs)
    {
        $this->_out(sprintf('q /GS%d gs', $gs));
    }

    protected function _enddoc()
    {
        if (!empty($this->extgstates) && $this->PDFVersion < '1.4') {
            $this->PDFVersion = '1.4';
        }
        parent::_enddoc();
    }

    protected function _putextgstates()
    {
        foreach ($this->extgstates as $i => $extgstate) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $parms = $extgstate['parms'];
            $this->_put(sprintf('/ca %s', $parms['ca']));
            $this->_put(sprintf('/CA %s', $parms['CA']));
            $this->_put(sprintf('/BM %s', $parms['BM']));
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    protected function _putresourcedict()
    {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        foreach ($this->extgstates as $i => $extgstate) {
            $this->_put(sprintf('/GS%d %d 0 R', $i, $extgstate['n']));
        }
        $this->_put('>>');
    }

    protected function _putresources()
    {
        $this->_putextgstates();
        parent::_putresources();
    }
}
