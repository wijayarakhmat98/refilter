--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3
-- Dumped by pg_dump version 15.3

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: sirup_penyedia; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sirup_penyedia (
    source integer NOT NULL,
    id integer NOT NULL,
    paket text,
    kldi text,
    satuan text,
    tahun integer,
    lokasi_pekerjaan_dump json[],
    volume text,
    uraian text,
    spesifikasi text,
    dalam_negeri boolean,
    usaha_kecil boolean,
    spp_ekonomi boolean,
    spp_sosial boolean,
    spp_lingkungan boolean,
    pra_dd boolean,
    kuappas text,
    sumber_dana text[],
    pagu bigint[],
    sumber_dana_dump json[],
    jenis text,
    total_pagu bigint,
    pemilihan text,
    pemanfaatan_awal date,
    pemanfaatan_akhir date,
    pelaksanaan_awal date,
    pelaksanaan_akhir date,
    pemilihan_awal date,
    pemilihan_akhir date,
    id_swakelola integer,
    history integer,
    perbarui timestamp without time zone
);


--
-- Name: sirup_penyedia sirup_penyedia_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sirup_penyedia
    ADD CONSTRAINT sirup_penyedia_pkey PRIMARY KEY (id);


--
-- Name: sirup_penyedia sirup_penyedia_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sirup_penyedia
    ADD CONSTRAINT sirup_penyedia_source_fkey FOREIGN KEY (source) REFERENCES public.raw(auto_id);


--
-- PostgreSQL database dump complete
--

