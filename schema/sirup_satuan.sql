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
-- Name: sirup_satuan; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sirup_satuan (
    source integer NOT NULL,
    id integer NOT NULL,
    kldi text,
    satuan text,
    nama text,
    alamat text,
    email text
);


--
-- Name: sirup_satuan sirup_satuan_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sirup_satuan
    ADD CONSTRAINT sirup_satuan_pkey PRIMARY KEY (id);


--
-- Name: sirup_satuan sirup_satuan_source_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sirup_satuan
    ADD CONSTRAINT sirup_satuan_source_fkey FOREIGN KEY (source) REFERENCES public.raw(auto_id);


--
-- PostgreSQL database dump complete
--

